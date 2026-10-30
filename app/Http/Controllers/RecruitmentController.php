<?php

namespace App\Http\Controllers;

use App\Actions\ConvertLeadToCandidate;
use App\Actions\CreateLead;
use App\Mail\ApplicationReceivedMail;
use App\Mail\PrequalificationCompletedMail;
use App\Mail\PrequalificationFormMail;
use App\Mail\CandidateAccessCredentialsMail;
use App\Mail\CandidateActivatedMail;
use App\Mail\ContractingRequirementsMail;
use App\Enums\CandidateStatus;
use App\Enums\CandidateType;
use App\Enums\LeadStatus;
use App\Http\Requests\StoreLeadRequest;
use App\Models\Candidate;
use App\Models\CandidateActivity;
use App\Models\ContractSlot;
use App\Models\LeadActivity;
use App\Models\LeadDocument;
use App\Models\Lead;
use App\Models\OnboardingDraft;
use App\Models\TrainingRecord;
use App\Models\OperationalProfile;
use App\Models\OperationalTrainingRecord;
use App\Models\StudioAccount;
use App\Services\ApplicationDiscardMailService;
use App\Services\AdmittedMailService;
use App\Services\RecruitmentDashboardService;
use App\Services\InterviewInvitationService;
use App\Services\ContractingInvitationService;
use App\Services\AuditLogger;
use App\Services\ProfilePhotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\User;

class RecruitmentController extends Controller
{
    public function apply(Request $request): Response
    {
        $type = $request->string('type', 'MODEL')->upper()->value();
        $draft = $request->cookie('velvet_visitor_token')
            ? OnboardingDraft::where('visitor_token', $request->cookie('velvet_visitor_token'))->first()
            : null;

        return Inertia::render('Public/Apply', [
            'type' => $type,
            'draft' => $draft ? ['current_step' => $draft->current_step, 'candidate_type' => $draft->candidate_type, 'data' => $draft->data] : null,
        ]);
    }

    public function store(StoreLeadRequest $request, CreateLead $createLead): Response
    {
        $lead = $createLead->handle($request->validated());

        if ($token = $request->cookie('velvet_visitor_token')) {
            OnboardingDraft::where('visitor_token', $token)->delete();
        }

        try {
            Mail::to($lead->email)->send(new ApplicationReceivedMail($lead));
        } catch (\Throwable $exception) {
            report($exception);
        }
        $this->publishLeadEvent($lead, 'lead.created', ['notification' => ['title' => 'Nuevo lead recibido', 'description' => "{$lead->full_name} envió una aplicación."]]);

        return Inertia::render('Public/ApplySuccess', ['lead' => ['code' => $lead->code, 'name' => $lead->full_name]]);
    }

    public function prequalification(Request $request, Candidate $candidate, string $token): Response
    {
        $this->validatePrequalificationLink($candidate, $token);

        return Inertia::render('Public/Prequalification', [
            'actionUrl' => $request->fullUrl(),
            'identitySessionUrl' => URL::temporarySignedRoute(
                'prequalification.identity.session',
                $candidate->prequalification_expires_at,
                ['candidate' => $candidate->id, 'token' => $token],
            ),
            'candidate' => [
                'id' => $candidate->id,
                'name' => $candidate->lead->full_name,
                'email' => $candidate->lead->email,
                'type' => $candidate->candidate_type?->value,
                'initial' => [
                    'availability' => $candidate->lead->availability,
                    'work_mode' => $candidate->lead->work_mode,
                    'experience' => $candidate->lead->experience,
                    'motivation' => $candidate->lead->motivation,
                ],
            ],
            'identityVerification' => [
                'enabled' => $this->diditIsConfigured(),
                'status' => $candidate->identity_verification_status ?: 'NOT_STARTED',
                'data' => Arr::only($candidate->identity_verification_data ?? [], [
                    'age_verified', 'name_verified', 'match_bypassed', 'date_of_birth', 'name', 'document_status', 'liveness_status', 'face_match_status', 'failure_reason',
                ]),
            ],
        ]);
    }

    public function prequalificationSuccess(Request $request): Response
    {
        return Inertia::render('Public/PrequalificationSuccess', [
            'candidate' => $request->session()->get('prequalification_completed'),
        ]);
    }

    public function storePrequalification(Request $request, Candidate $candidate, string $token): Response|RedirectResponse
    {
        $this->validatePrequalificationLink($candidate, $token);

        $validated = $request->validate([
            'availability' => ['required', 'in:Tiempo completo,Medio tiempo,Por horas'],
            'work_mode' => ['required', 'string', 'max:100'],
            'experience' => ['required', 'string', 'min:20', 'max:5000'],
            'motivation' => ['required', 'string', 'min:20', 'max:5000'],
            'social_networks' => ['nullable', 'string', 'max:500'],
            'identity_document' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:5120'],
            'accept_terms' => ['accepted'],
        ]);

        if ($this->diditIsConfigured() && ! $this->identityVerificationIsApproved($candidate)) {
            throw ValidationException::withMessages([
                'identity_verification' => 'Completa y aprueba la verificación de identidad antes de enviar los requisitos.',
            ]);
        }

        if (! $this->diditIsConfigured() && empty($validated['identity_document'])) {
            throw ValidationException::withMessages([
                'identity_document' => 'Carga el documento de identidad mientras se configura la verificación automática.',
            ]);
        }

        $document = $validated['identity_document'] ?? null;
        $data = Arr::except($validated, ['identity_document', 'accept_terms']);

        $activity = DB::transaction(function () use ($candidate, $document, $data): CandidateActivity {
            $candidate->lead->update([
                'availability' => $data['availability'],
                'work_mode' => $data['work_mode'],
                'experience' => $data['experience'],
                'motivation' => $data['motivation'],
            ]);

            if ($document) {
                LeadDocument::create([
                    'lead_id' => $candidate->lead_id,
                    'type' => 'identity',
                    'original_name' => $document->getClientOriginalName(),
                    'path' => $document->store('lead-documents', 'local'),
                    'mime_type' => $document->getMimeType(),
                    'size' => $document->getSize(),
                ]);
            }

            $candidate->update([
                'status' => CandidateStatus::PREQUALIFIED,
                'prequalification_data' => $data,
                'prequalification_completed_at' => now(),
                'prequalification_token_hash' => null,
                'prequalification_expires_at' => null,
            ]);
            return CandidateActivity::create([
                'candidate_id' => $candidate->id,
                'type' => 'prequalification_completed',
                'description' => 'Formulario de requisitos iniciales completado. Candidata precalificada.',
            ]);
        });

        // Notificar el avance no debe impedir que la candidata vea la
        // confirmación si el proveedor de correo tiene una interrupción temporal.
        try {
            $candidate->loadMissing('lead');
            Mail::to($candidate->lead->email)->send(new PrequalificationCompletedMail($candidate));
        } catch (\Throwable $exception) {
            report($exception);
        }

        $lead = $candidate->load('lead')->lead->fresh('candidate');
        $this->publishLeadEvent($lead, 'candidate.prequalification_completed', [
            'candidate_id' => $candidate->id,
            'status' => CandidateStatus::PREQUALIFIED->value,
            'activity' => $activity->only(['id', 'description', 'created_at']),
            'notification' => [
                'title' => 'Precalificación completada',
                'description' => "{$candidate->lead->full_name} completó los requisitos iniciales.",
            ],
        ]);

        return to_route('prequalification.success')->with('prequalification_completed', [
            'name' => $candidate->lead->full_name,
            'code' => $candidate->code,
        ]);
    }

    public function createIdentityVerificationSession(Request $request, Candidate $candidate, string $token): JsonResponse
    {
        $this->validatePrequalificationLink($candidate, $token);

        if (! $this->diditIsConfigured()) {
            return response()->json([
                'message' => 'La verificación de identidad aún no está configurada.',
            ], 503);
        }

        if ($request->boolean('sync') && $candidate->didit_session_id) {
            try {
                $decision = Http::timeout(20)
                    ->acceptJson()
                    ->withHeaders(['x-api-key' => config('services.didit.api_key')])
                    ->get(rtrim(config('services.didit.base_url'), '/') . "/v3/session/{$candidate->didit_session_id}/decision/");

                if ($decision->successful() && is_array($decision->json())) {
                    $this->syncDiditDecision($candidate, $decision->json(), $candidate->didit_session_id);
                    $candidate->refresh();

                    return response()->json([
                        'status' => $candidate->identity_verification_status,
                        'data' => $candidate->identity_verification_data,
                    ]);
                }
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        if ($this->identityVerificationIsApproved($candidate)) {
            return response()->json([
                'status' => 'APPROVED',
                'message' => 'La identidad ya fue verificada.',
            ]);
        }

        if (! $request->boolean('restart')
            && $candidate->identity_verification_status === 'IN_PROGRESS'
            && $candidate->identity_verification_url) {
            return response()->json([
                'status' => 'IN_PROGRESS',
                'url' => $candidate->identity_verification_url,
            ]);
        }

        // Didit usa `callback` para devolver al navegador al terminar la verificación.
        // El webhook POST se configura por separado en la consola de Didit.
        $callbackUrl = route('prequalification.identity.callback', [
            'candidate' => $candidate->id,
            'token' => $token,
        ]);
        $response = Http::timeout(20)
            ->acceptJson()
            ->withHeaders(['x-api-key' => config('services.didit.api_key')])
            ->post(rtrim(config('services.didit.base_url'), '/') . '/v3/session/', [
                'workflow_id' => config('services.didit.workflow_id'),
                'vendor_data' => "candidate:{$candidate->id}",
                'callback' => $callbackUrl,
                'callback_method' => 'both',
                'metadata' => [
                    'candidate_id' => (string) $candidate->id,
                    'candidate_code' => $candidate->code,
                ],
            ]);

        if ($response->failed()) {
            report(new \RuntimeException('Didit session creation failed: ' . $response->body()));

            return response()->json([
                'message' => 'No se pudo iniciar la verificación. Inténtalo nuevamente.',
            ], 502);
        }

        $sessionId = $response->json('session_id');
        $sessionUrl = $response->json('url') ?: $response->json('session_url');
        if (! $sessionId || ! $sessionUrl) {
            report(new \UnexpectedValueException('Didit response did not include a session URL.'));

            return response()->json([
                'message' => 'La respuesta de verificación no fue válida.',
            ], 502);
        }

        $candidate->update([
            'identity_verification_status' => 'IN_PROGRESS',
            'didit_session_id' => $sessionId,
            'identity_verification_url' => $sessionUrl,
            'identity_verification_failure_reason' => null,
        ]);
        $activity = CandidateActivity::create([
            'candidate_id' => $candidate->id,
            'type' => 'identity_verification_started',
            'description' => 'Verificación de identidad iniciada.',
            'metadata' => ['provider' => 'didit', 'session_id' => $sessionId],
        ]);

        return response()->json(['status' => 'IN_PROGRESS', 'url' => $sessionUrl]);
    }

    public function diditCallback(Request $request, Candidate $candidate, string $token): RedirectResponse
    {
        // El callback es público porque Didit lo visita con GET, pero el token
        // aleatorio y su expiración siguen protegiendo el formulario.
        $this->validatePrequalificationLink($candidate, $token);

        $expiresAt = $candidate->prequalification_expires_at ?: now()->addMinutes(15);
        $returnUrl = URL::temporarySignedRoute('prequalification.show', $expiresAt, [
            'candidate' => $candidate->id,
            'token' => $token,
            'identity_status' => $request->query('status'),
            'verification_session_id' => $request->query('verificationSessionId'),
        ]);

        // El webhook es la fuente principal, pero consultar la decisión aquí
        // permite actualizar la pantalla inmediatamente y cubre sesiones de
        // prueba cuando todavía no se ha configurado el destino en Didit.
        $sessionId = $request->query('verificationSessionId');
        if ($sessionId && $this->diditIsConfigured()) {
            try {
                $decision = Http::timeout(20)
                    ->acceptJson()
                    ->withHeaders(['x-api-key' => config('services.didit.api_key')])
                    ->get(rtrim(config('services.didit.base_url'), '/') . "/v3/session/{$sessionId}/decision/");

                if ($decision->successful() && is_array($decision->json())) {
                    $this->syncDiditDecision($candidate, $decision->json(), $sessionId);
                }
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return redirect()->to($returnUrl);
    }

    public function diditWebhook(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        $secret = (string) config('services.didit.webhook_secret');

        abort_unless($secret && $this->diditSignatureIsValid($request, $rawBody, $secret), 401, 'Firma de webhook inválida.');

        try {
            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return response()->json(['message' => 'JSON inválido.'], 400);
        }

        $sessionId = data_get($payload, 'session_id') ?: data_get($payload, 'data.session_id');
        $vendorData = data_get($payload, 'vendor_data') ?: data_get($payload, 'data.vendor_data');
        $candidateId = is_string($vendorData) && preg_match('/^candidate:(\d+)$/', $vendorData, $matches) ? (int) $matches[1] : null;
        $candidate = ($sessionId ? Candidate::where('didit_session_id', $sessionId)->first() : null) ?: ($candidateId ? Candidate::find($candidateId) : null);

        // Acknowledge unknown or duplicated sessions so Didit does not retry forever.
        if (! $candidate) {
            return response()->json(['received' => true]);
        }

        $this->syncDiditDecision($candidate, $payload, $sessionId);

        return response()->json(['received' => true]);
    }

    private function syncDiditDecision(Candidate $candidate, array $payload, ?string $sessionId = null): void
    {
        $providerStatus = data_get($payload, 'status') ?: data_get($payload, 'data.status') ?: data_get($payload, 'decision.status');
        $status = match ($providerStatus) {
            'Approved' => 'APPROVED',
            'In Review' => 'IN_REVIEW',
            'Declined' => 'DECLINED',
            'Expired' => 'EXPIRED',
            'Abandoned' => 'ABANDONED',
            'Resubmitted', 'Not Finished' => 'IN_PROGRESS',
            default => 'IN_PROGRESS',
        };
        $summary = $this->diditIdentitySummary($payload, $candidate, $status);
        if ($status === 'APPROVED' && app()->environment('local')) {
            $summary['match_bypassed'] = true;
            $summary['age_verified'] = true;
            $summary['name_verified'] = true;
        } elseif ($status === 'APPROVED' && (! $summary['age_verified'] || ! $summary['name_verified'])) {
            $status = 'IN_REVIEW';
            $reasons = [];
            if (! $summary['age_verified']) {
                $reasons[] = 'la mayoría de edad o la fecha de nacimiento';
            }
            if (! $summary['name_verified']) {
                $reasons[] = 'el nombre del documento';
            }
            $summary['failure_reason'] = 'Didit aprobó el documento, pero no coincide ' . implode(' ni ', $reasons) . ' registrada en el onboarding.';
        }

        $sameDecision = $candidate->didit_session_id === ($sessionId ?: $candidate->didit_session_id)
            && $candidate->identity_verification_status === $status;
        $candidate->update([
            'identity_verification_status' => $status,
            'didit_session_id' => $sessionId ?: $candidate->didit_session_id,
            'identity_verification_data' => $summary,
            'identity_verified_at' => $status === 'APPROVED' ? now() : null,
            'identity_verification_failure_reason' => $summary['failure_reason'] ?? null,
        ]);
        if ($status === 'APPROVED') {
            app(ProfilePhotoService::class)->importDefaultFromDidit($candidate);
        }
        if (! $sameDecision) {
            $activity = CandidateActivity::create([
                'candidate_id' => $candidate->id,
                'type' => 'identity_verification_updated',
                'description' => match ($status) {
                    'APPROVED' => 'Identidad verificada y mayoría de edad confirmada.',
                    'DECLINED' => 'La verificación de identidad fue rechazada.',
                    'IN_REVIEW' => 'Didit aprobó el documento, pero los datos requieren revisión manual.',
                    default => 'Estado de la verificación de identidad actualizado.',
                },
                'metadata' => ['provider' => 'didit', 'session_id' => $sessionId, 'status' => $status],
            ]);
            $candidate->load('lead');
            $this->publishLeadEvent($candidate->lead, 'candidate.identity_updated', ['candidate_id' => $candidate->id, 'activity' => $activity->only(['id', 'description', 'created_at']), 'notification' => ['title' => 'Validación de identidad actualizada', 'description' => "{$candidate->lead->full_name}: {$status}."]]);
        }
    }

    public function dashboard(Request $request, RecruitmentDashboardService $dashboard): Response|RedirectResponse
    {
        if ($request->user()->hasRole('model')) {
            return to_route('model.dashboard');
        }

        if ($request->user()->hasRole('monitor')) {
            return to_route('monitor.dashboard');
        }

        $request->validate([
            'period' => ['nullable', 'in:today,7d,30d,month,custom'],
            'type' => ['nullable', 'in:MODEL,MONITOR'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return Inertia::render('Admin/Recruitment/Dashboard', [
            ...$dashboard->handle($request),
            'user' => ['name' => $request->user()->name, 'email' => $request->user()->email],
        ]);
    }

    public function workflows(): Response
    {
        $counts = Candidate::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total) => (int) $total)
            ->all();

        $status = fn (string $key, string $label, string $description, string $tone = 'purple', ?string $from = null) => [
            'key' => $key,
            'label' => $label,
            'description' => $description,
            'count' => $counts[$key] ?? 0,
            'tone' => $tone,
            'from' => $from,
            'kind' => 'status',
        ];
        $step = fn (string $key, string $label, string $description, string $tone = 'purple', ?string $from = null) => [
            'key' => $key,
            'label' => $label,
            'description' => $description,
            'tone' => $tone,
            'from' => $from,
            'kind' => 'operation',
        ];

        return Inertia::render('Admin/Recruitment/Workflows/Index', [
            'flows' => [
                [
                    'id' => 'admission',
                    'name' => 'Admisión de candidatos',
                    'description' => 'Ruta principal desde la solicitud hasta la admisión y el inicio del onboarding.',
                    'nodes' => [
                        $status('NEW', 'Nuevo', 'Solicitud recibida'),
                        $status('CONTACTED', 'Contactado', 'Primer contacto'),
                        $status('PREQUALIFIED', 'Precalificado', 'Requisitos completos', 'blue'),
                        $status('INTERVIEW', 'Entrevista', 'Cita y evaluación inicial', 'blue'),
                        $status('EVALUATION', 'Evaluación', 'Revisión del equipo', 'blue'),
                        $status('ADMITTED', 'Admitido', 'Ingreso aprobado', 'green'),
                        $status('WAITING', 'En espera', 'Requisitos de contratación', 'amber'),
                        $status('CONTRACTING', 'Contratación', 'Formalización del ingreso', 'blue'),
                        $status('ONBOARDING', 'Onboarding', 'Documentación de ingreso', 'green'),
                    ],
                    'branches' => [
                        $status('DISCARDED', 'Descartado', 'No continúa el proceso', 'red', 'EVALUATION'),
                    ],
                ],
                [
                    'id' => 'activation',
                    'name' => 'Activación y permanencia',
                    'description' => 'Seguimiento desde el onboarding hasta la activación operativa del talento.',
                    'nodes' => [
                        $status('ONBOARDING', 'Onboarding', 'Documentación de ingreso', 'green'),
                        $status('INDUCTION', 'Inducción', 'Preparación inicial', 'blue'),
                        $status('READY_TO_ACTIVATE', 'Listo para activar', 'Revisión final', 'green'),
                        $status('ACTIVE', 'Activo', 'Talento habilitado', 'green'),
                    ],
                    'branches' => [$status('WITHDRAWN', 'Retirado', 'Proceso cerrado', 'red', 'ONBOARDING')],
                ],
                [
                    'id' => 'prequalification',
                    'name' => 'Precalificación e identidad',
                    'description' => 'Cómo se solicita el formulario, se valida la identidad con Didit y se habilita el avance a entrevista.',
                    'nodes' => [
                        $step('candidate-ready', 'Candidato elegible', 'Lead convertido y pendiente de requisitos'),
                        $step('send-form', 'Enviar precalificación', 'Se genera un enlace personal con vencimiento'),
                        $step('person-completes', 'Completar requisitos', 'La persona actualiza datos y adjunta soporte', 'blue'),
                        $step('didit', 'Validar en Didit', 'Documento, selfie y mayoría de edad', 'blue'),
                        $step('review-validation', 'Revisar validación', 'El equipo consulta el resultado en Validaciones', 'amber'),
                        $status('PREQUALIFIED', 'Precalificado', 'Requisitos completos y aprobados', 'green'),
                    ],
                    'branches' => [$step('identity-retry', 'Corregir o repetir', 'Se solicita una nueva validación si Didit la rechaza', 'red', 'didit')],
                ],
                [
                    'id' => 'interviews',
                    'name' => 'Horarios y entrevistas',
                    'description' => 'Flujo para crear franjas, invitar al candidato, confirmar la cita y llevarla a evaluación.',
                    'nodes' => [
                        $status('PREQUALIFIED', 'Precalificado', 'Perfil listo para entrevista', 'green'),
                        $step('generate-slots', 'Crear horarios', 'El equipo genera franjas disponibles de una hora', 'blue'),
                        $step('send-invitation', 'Enviar invitación', 'La persona recibe un enlace para elegir horario'),
                        $status('INTERVIEW', 'Cita confirmada', 'La franja seleccionada queda reservada', 'blue'),
                        $step('record-notes', 'Registrar resultado', 'Se guardan notas y evidencias de la entrevista', 'amber'),
                        $status('EVALUATION', 'Evaluación', 'El equipo decide si admite o no al candidato', 'blue'),
                    ],
                    'branches' => [
                        $step('reschedule', 'Reprogramar', 'Libera la franja y envía nuevos horarios', 'amber', 'INTERVIEW'),
                        $step('no-show', 'No asistió', 'Se registra la novedad para seguimiento', 'red', 'INTERVIEW'),
                    ],
                ],
                [
                    'id' => 'contracting',
                    'name' => 'Contratación y documentos',
                    'description' => 'Ruta desde la admisión: requisitos, carga y validación documental, cita y paso a onboarding.',
                    'nodes' => [
                        $status('ADMITTED', 'Admitido', 'Ingreso aprobado por el equipo', 'green'),
                        $step('start-contracting', 'Iniciar contratación', 'Se envía el listado de requisitos documentales', 'amber'),
                        $status('WAITING', 'Esperar soportes', 'La persona carga los documentos solicitados', 'amber'),
                        $step('verify-documents', 'Verificar documentos', 'Documentos revisa, aprueba o pide corrección', 'blue'),
                        $step('contract-slots', 'Crear cita', 'Se generan y reservan franjas de contratación'),
                        $status('CONTRACTING', 'Contratación', 'Cita y formalización en curso', 'blue'),
                        $status('ONBOARDING', 'Onboarding', 'Requisitos de ingreso completos', 'green'),
                    ],
                    'branches' => [$step('document-correction', 'Solicitar corrección', 'La persona vuelve a cargar el soporte requerido', 'red', 'verify-documents')],
                ],
                [
                    'id' => 'rooms',
                    'name' => 'Rooms y reservas',
                    'description' => 'Cómo se solicita una room, se confirma su disponibilidad y se registra el uso operativo.',
                    'nodes' => [
                        $step('room-available', 'Room disponible', 'Administración configura capacidad y disponibilidad', 'green'),
                        $step('request-room', 'Solicitar reserva', 'Modelo, monitor o equipo pide una franja'),
                        $step('confirm-room', 'Confirmar reserva', 'El sistema evita cruces y bloquea el horario', 'blue'),
                        $step('start-room-use', 'Registrar inicio', 'Se marca el comienzo del uso de la room', 'amber'),
                        $step('finish-room-use', 'Registrar cierre', 'Se cierra el uso y queda trazabilidad', 'green'),
                    ],
                    'branches' => [$step('release-room', 'Cancelar o liberar', 'La franja vuelve a estar disponible', 'red', 'confirm-room')],
                ],
                [
                    'id' => 'calendar',
                    'name' => 'Calendario y seguimiento',
                    'description' => 'Flujo de recordatorios internos y fechas clave que se originan desde reclutamiento y operación.',
                    'nodes' => [
                        $step('detect-deadline', 'Detectar hito', 'Vencimiento, cita, entrevista o tarea pendiente', 'amber'),
                        $step('create-event', 'Crear evento', 'Se registra recordatorio o seguimiento en Calendario'),
                        $step('assign-event', 'Asignar responsable', 'El equipo recibe contexto y fecha de ejecución', 'blue'),
                        $step('complete-event', 'Completar actividad', 'Se conserva el cierre y la trazabilidad', 'green'),
                    ],
                    'branches' => [$step('reschedule-event', 'Editar o reprogramar', 'Se ajusta la fecha sin perder el contexto', 'amber', 'assign-event')],
                ],
                [
                    'id' => 'access',
                    'name' => 'Accesos y operación',
                    'description' => 'Cómo se crean cuentas internas, se asignan roles y se mantienen los permisos de cada módulo.',
                    'nodes' => [
                        $step('create-user', 'Crear usuario', 'Administración registra la cuenta interna'),
                        $step('assign-role', 'Asignar rol', 'Roles y permisos determinan los módulos visibles', 'blue'),
                        $step('activate-access', 'Activar acceso', 'La persona puede ingresar según su perfil', 'green'),
                        $step('audit-access', 'Revisar actividad', 'Notificaciones e historial apoyan el seguimiento', 'amber'),
                    ],
                    'branches' => [$step('suspend-access', 'Suspender acceso', 'Se bloquea el ingreso sin borrar historial', 'red', 'audit-access')],
                ],
                [
                    'id' => 'notifications',
                    'name' => 'Notificaciones y alertas',
                    'description' => 'Cómo los cambios importantes de cada módulo llegan al equipo y lo llevan de vuelta al caso correspondiente.',
                    'nodes' => [
                        $step('business-event', 'Cambio operativo', 'Una acción en leads, candidatos, documentos, citas o rooms genera un evento', 'blue'),
                        $step('publish-alert', 'Publicar alerta', 'El sistema actualiza contadores y crea la notificación', 'purple'),
                        $step('team-receives', 'Equipo recibe aviso', 'La campana y el historial muestran el contexto', 'amber'),
                        $step('open-related-case', 'Abrir caso relacionado', 'La persona vuelve al perfil o módulo que requiere atención', 'green'),
                    ],
                    'branches' => [$step('mark-read', 'Marcar como leída', 'La alerta queda en el historial para consulta posterior', 'green', 'team-receives')],
                ],
                [
                    'id' => 'training',
                    'name' => 'Datos de prueba e inducción',
                    'description' => 'Ruta segura para crear escenarios de práctica, entrenar equipos y separar la inducción de la operación real.',
                    'nodes' => [
                        $step('prepare-sandbox', 'Preparar sandbox', 'Super administración crea o actualiza datos de entrenamiento', 'purple'),
                        $step('assign-training-profile', 'Asignar perfil de práctica', 'Modelo o monitor ingresa con alcance controlado', 'blue'),
                        $step('practice-operation', 'Practicar operación', 'Se ensayan reservas, perfiles y procesos sin tocar datos reales', 'amber'),
                        $step('activate-real-operation', 'Habilitar operación real', 'Tras completar inducción se activa el perfil productivo', 'green'),
                    ],
                    'branches' => [$step('reset-sandbox', 'Reiniciar escenario', 'Los datos de prueba se regeneran para una nueva práctica', 'red', 'practice-operation')],
                ],
            ],
            'summary' => [
                'total' => Candidate::count(),
                'active' => $counts['ACTIVE'] ?? 0,
                'admitted' => $counts['ADMITTED'] ?? 0,
                'inProgress' => collect($counts)->only(['CONTACTED', 'PREQUALIFIED', 'INTERVIEW', 'EVALUATION', 'WAITING', 'CONTRACTING', 'ONBOARDING', 'INDUCTION', 'READY_TO_ACTIVATE'])->sum(),
            ],
        ]);
    }

    public function processes(Request $request): Response
    {
        $query = Lead::query()->with(['candidate.interviews'])->latest('updated_at');
        $search = $request->string('search')->trim()->value();
        $candidateStatuses = array_column(CandidateStatus::cases(), 'value');
        $leadStatuses = array_column(LeadStatus::cases(), 'value');

        if ($search) {
            $query->where(fn ($searchQuery) => $searchQuery
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%"));
        }
        if ($type = $request->string('type')->upper()->value()) {
            $query->where('candidate_type', $type);
        }
        if ($status = $request->string('status')->upper()->value()) {
            if (in_array($status, $candidateStatuses, true)) {
                $query->whereHas('candidate', fn ($candidateQuery) => $candidateQuery->where('status', $status));
            } elseif (in_array($status, $leadStatuses, true)) {
                $query->where('status', $status);
            }
        }

        $processes = $query->paginate(15)->withQueryString()->through(function (Lead $lead): array {
            $candidate = $lead->candidate;
            $stage = $candidate?->status?->value ?: $lead->status?->value;

            return [
                'id' => $lead->id,
                'candidate_id' => $candidate?->id,
                'code' => $candidate?->code ?: $lead->code,
                'name' => $lead->full_name,
                'email' => $lead->email,
                'type' => $lead->candidate_type?->value,
                'lead_status' => $lead->status?->value,
                'stage' => $stage,
                'stage_label' => $candidate?->status?->label() ?: ($lead->status?->value === 'CONVERTED' ? 'Convertido' : ucfirst(strtolower($lead->status?->value ?? 'Nuevo'))),
                'identity_status' => $candidate?->identity_verification_status,
                'prequalification_completed' => (bool) $candidate?->prequalification_completed_at,
                'interview_scheduled' => (bool) $candidate?->interviews?->whereIn('status', ['SCHEDULED', 'CONFIRMED'])->count(),
                'updated_at' => $lead->updated_at?->toIso8601String(),
            ];
        });

        return Inertia::render('Admin/Recruitment/Processes/Index', [
            'processes' => $processes,
            'filters' => $request->only('search', 'type', 'status'),
            'stageOptions' => collect(CandidateStatus::cases())->mapWithKeys(fn ($status) => [$status->value => $status->label()])->all(),
        ]);
    }

    public function leads(Request $request): Response
    {
        $query = Lead::query()->with(['assignee', 'candidate'])->latest();
        if ($search = $request->string('search')->trim()->value()) {
            $query->where(fn ($q) => $q->where('code', 'like', "%{$search}%")->orWhere('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"));
        }
        if ($type = $request->string('type')->value()) {
            $query->where('candidate_type', $type);
        }
        if ($status = $request->string('status')->value()) {
            $query->where('status', $status);
        }

        $leads = $query->paginate(15)->withQueryString()->through(function (Lead $lead): array {
            return [
                ...Arr::only($lead->toArray(), ['id', 'code', 'first_name', 'last_name', 'email', 'phone', 'candidate_type', 'city', 'status', 'created_at', 'updated_at']),
                'application_status' => $lead->candidate?->status?->value ?: $lead->status?->value,
                'application_status_label' => $lead->candidate?->status?->label() ?: match ($lead->status?->value) {
                    'NEW' => 'Nueva',
                    'CONTACTED' => 'Contactada',
                    'QUALIFIED' => 'Calificada',
                    'UNRESPONSIVE' => 'Sin respuesta',
                    'DISCARDED' => 'Descartada',
                    'CONVERTED' => 'Convertida',
                    default => 'Sin estado',
                },
            ];
        });

        return Inertia::render('Admin/Recruitment/Leads/Index', ['leads' => $leads, 'filters' => $request->only('search', 'type', 'status')]);
    }

    public function validations(Request $request): Response
    {
        $query = Candidate::query()
            ->with('lead')
            ->whereNotNull('didit_session_id')
            ->latest('updated_at');

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(fn ($searchQuery) => $searchQuery
                ->whereHas('lead', fn ($lead) => $lead
                    ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"))
                ->orWhere('code', 'like', "%{$search}%"));
        }

        if ($status = $request->string('status')->value()) {
            $query->where('identity_verification_status', $status);
        }

        $validations = $query->paginate(15)->withQueryString()->through(fn (Candidate $candidate) => [
            'id' => $candidate->id,
            'code' => $candidate->code,
            'name' => $candidate->lead?->full_name,
            'email' => $candidate->lead?->email,
            'candidate_status' => $candidate->status?->value,
            'identity_status' => $candidate->identity_verification_status ?: 'NOT_STARTED',
            'failure_reason' => $candidate->identity_verification_failure_reason,
            'session_id' => $candidate->didit_session_id,
            'verified_at' => $candidate->identity_verified_at?->toIso8601String(),
            'data' => $candidate->identity_verification_data ?? [],
        ]);

        return Inertia::render('Admin/Recruitment/Validations/Index', [
            'validations' => $validations,
            'filters' => $request->only('search', 'status'),
        ]);
    }

    public function lead(Lead $lead): Response
    {
        $lead->load(['activities.user', 'candidate.activities.user', 'documents']);
        $processActivities = $lead->activities
            ->map(fn (LeadActivity $activity) => array_merge($activity->toArray(), ['description' => $this->localizeActivityDescription($activity->description), 'source' => 'lead', 'timeline_id' => "lead-{$activity->id}"]))
            ->concat($lead->candidate?->activities?->map(fn (CandidateActivity $activity) => array_merge($activity->toArray(), ['description' => $this->localizeActivityDescription($activity->description), 'source' => 'candidate', 'timeline_id' => "candidate-{$activity->id}"])) ?? collect())
            ->sortBy('created_at')
            ->values()
            ->all();
        $statusEnum = $lead->candidate ? CandidateStatus::cases() : LeadStatus::cases();

        return Inertia::render('Admin/Recruitment/Leads/Show', ['lead' => array_merge($lead->toArray(), [
            'process_activities' => $processActivities,
            'pipeline_status' => $lead->candidate?->status?->value ?: $lead->status?->value,
            'pipeline_status_label' => $lead->candidate?->status?->label() ?: ($lead->status?->value === 'NEW' ? 'Nueva' : ucfirst(strtolower($lead->status?->value ?? ''))),
            'status_options' => collect($statusEnum)->map(fn ($status) => ['value' => $status->value, 'label' => method_exists($status, 'label') ? $status->label() : match ($status) {
                LeadStatus::NEW => 'Nueva', LeadStatus::CONTACTED => 'Contactada', LeadStatus::QUALIFIED => 'Calificada', LeadStatus::UNRESPONSIVE => 'Sin respuesta', LeadStatus::DISCARDED => 'Descartada', LeadStatus::CONVERTED => 'Convertida',
            }])->values()->all(),
        ])]);
    }

    public function updateLeadStatus(Request $request, Lead $lead, InterviewInvitationService $invitationService, ApplicationDiscardMailService $discardMail, AdmittedMailService $admittedMail): RedirectResponse
    {
        $lead->loadMissing('candidate.user');
        $allowed = $lead->candidate ? CandidateStatus::cases() : LeadStatus::cases();
        $validated = $request->validate(['status' => ['required', Rule::in(array_map(fn ($status) => $status->value, $allowed))]]);
        $previous = $lead->candidate?->status?->value ?: $lead->status?->value;
        $discardTransition = $validated['status'] === CandidateStatus::DISCARDED->value
            && $previous !== CandidateStatus::DISCARDED->value
            && $previous !== LeadStatus::DISCARDED->value;
        $interview = null;

        if ($lead->candidate && $validated['status'] === CandidateStatus::INTERVIEW->value && $previous !== CandidateStatus::INTERVIEW->value) {
            try {
                $interview = $invitationService->send($lead->candidate, $request->user(), false);
            } catch (\Throwable $exception) {
                report($exception);

                return back()->withErrors(['status' => 'No se pudo enviar el formulario de horarios. Revisa la configuración de correo.']);
            }

            if (! $interview) {
                return back()->withErrors(['status' => 'No hay horarios disponibles. Genera al menos una franja antes de pasar a entrevista.']);
            }
        }

        if ($lead->candidate && $validated['status'] === CandidateStatus::ADMITTED->value && $previous !== CandidateStatus::EVALUATION->value) {
            return back()->withErrors(['status' => 'El candidato debe estar en evaluación antes de ser admitido.']);
        }

        if ($lead->candidate && $validated['status'] === CandidateStatus::WAITING->value && $previous === CandidateStatus::ADMITTED->value) {
            return back()->withErrors(['status' => 'Inicia la contratacion desde la accion recomendada para enviar los requisitos por correo.']);
        }

        if ($lead->candidate && $validated['status'] === CandidateStatus::CONTRACTING->value && $previous !== CandidateStatus::WAITING->value) {
            return back()->withErrors(['status' => 'El candidato debe estar admitido antes de iniciar la contratación.']);
        }

        if ($lead->candidate && $validated['status'] === CandidateStatus::ONBOARDING->value && $previous !== CandidateStatus::CONTRACTING->value) {
            return back()->withErrors(['status' => 'El candidato debe completar la contratación antes de pasar a onboarding.']);
        }

        if ($lead->candidate && $validated['status'] === CandidateStatus::ACTIVE->value && (! $lead->candidate->user_id || ! $lead->candidate->user?->is_active)) {
            return back()->withErrors(['status' => 'Activa primero el acceso al dashboard desde el panel administrativo.']);
        }

        if ($lead->candidate) {
            $candidateAttributes = ['status' => $validated['status']];
            if ($discardTransition && ! $lead->candidate->discarded_at) {
                $candidateAttributes['discarded_at'] = now();
            }
            if ($discardTransition && $request->filled('reason')) {
                $candidateAttributes['discard_reason'] = $request->string('reason')->trim()->value();
            }
            $lead->candidate->update($candidateAttributes);
        } else {
            $leadAttributes = ['status' => $validated['status']];
            if ($validated['status'] === LeadStatus::DISCARDED->value && $request->filled('reason')) {
                $leadAttributes['discard_reason'] = $request->string('reason')->trim()->value();
            }
            $lead->update($leadAttributes);
        }

        $previousLabel = $lead->candidate
            ? CandidateStatus::tryFrom($previous)?->label()
            : LeadStatus::tryFrom($previous)?->label();
        $nextLabel = $lead->candidate
            ? CandidateStatus::tryFrom($validated['status'])?->label()
            : LeadStatus::tryFrom($validated['status'])?->label();

        $activity = $lead->candidate
            ? CandidateActivity::create([
                'candidate_id' => $lead->candidate->id,
                'user_id' => $request->user()->id,
                'type' => 'status_changed',
                'description' => "Estado cambiado de {$previousLabel} a {$nextLabel}.",
                'metadata' => ['from' => $previous, 'to' => $validated['status']],
            ])
            : LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => $request->user()->id,
                'type' => 'status_changed',
                'description' => "Estado cambiado de {$previousLabel} a {$nextLabel}.",
                'metadata' => ['from' => $previous, 'to' => $validated['status']],
            ]);
        $this->publishLeadEvent($lead->fresh('candidate'), $lead->candidate ? 'candidate.status_changed' : 'lead.status_changed', [
            'activity' => ['id' => $activity->id, 'description' => $activity->description, 'created_at' => $activity->created_at?->toIso8601String()],
            'activities' => collect([$interview?->getRelation('invitationActivity'), $activity])
                ->filter()
                ->map(fn (CandidateActivity|LeadActivity $item) => ['id' => $item->id, 'description' => $item->description, 'created_at' => $item->created_at?->toIso8601String()])
                ->values()
                ->all(),
            'notification' => ['title' => 'Etapa actualizada', 'description' => "{$lead->full_name}: {$nextLabel}."],
        ]);

        if ($discardTransition) {
            $discardMail->send($lead->fresh('candidate'), $previous ?: LeadStatus::NEW->value);
        }

        if ($lead->candidate && $validated['status'] === CandidateStatus::ADMITTED->value && $previous !== CandidateStatus::ADMITTED->value) {
            $admittedMail->send($lead->fresh('candidate'));
        }

        return back()->with('success', 'Estado actualizado correctamente.');
    }

    public function ablyToken(): JsonResponse
    {
        abort_unless(auth()->user()?->hasAnyPermission(['leads.view', 'stations.assign']), 403);
        abort_unless(config('services.ably.api_key'), 503, 'Ably no está configurado.');
        [$keyName, $keySecret] = explode(':', config('services.ably.api_key'), 2);
        $response = Http::withBasicAuth($keyName, $keySecret)
            ->acceptJson()->post("https://rest.ably.io/keys/{$keyName}/requestToken", [
                'keyName' => $keyName,
                'capability' => json_encode(collect(config('services.ably.channels', []))->mapWithKeys(fn ($channel) => [$channel => ['subscribe']])->all()),
                'ttl' => 3600000,
                'timestamp' => now()->valueOf(),
            ]);
        if (! $response->successful()) {
            logger()->error('Ably rechazó la solicitud de token.', [
                'status' => $response->status(),
                'body' => $response->body(),
                'channels' => config('services.ably.channels'),
            ]);
        }
        abort_unless($response->successful(), 502, 'No se pudo autenticar con Ably.');

        return response()->json($response->json());
    }

    private function publishLeadEvent(Lead $lead, string $name, array $extra = []): void
    {
        app(\App\Services\RealtimePublisher::class)->publishLead($lead, $name, $extra);
    }

    public function updateLead(Request $request, Lead $lead): RedirectResponse
    {
        $validated = $request->validate($this->leadDataRules($request));
        $identityDataChanged = $this->identityDataChanged($validated, $lead);
        $lead->update(Arr::except($validated, ['candidate_type']));
        if ($lead->candidate) {
            $lead->candidate->update(array_merge(
                ['candidate_type' => $validated['candidate_type']],
                $identityDataChanged ? $this->identityResetAttributes() : [],
            ));
        }
        $activity = LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => $request->user()->id,
            'type' => 'data_updated',
            'description' => 'Información de la aplicación actualizada por el equipo.',
            'metadata' => ['fields' => array_keys($validated), 'identity_verification_reset' => $identityDataChanged],
        ]);
        $this->publishLeadEvent($lead->fresh('candidate'), 'lead.updated', ['activity' => $activity->only(['id', 'description', 'created_at']), 'notification' => ['title' => 'Datos actualizados', 'description' => "Se actualizaron los datos de {$lead->full_name}."]]);

        return back()->with('success', 'Información del lead actualizada correctamente.');
    }

    public function discardLead(Request $request, Lead $lead, ApplicationDiscardMailService $discardMail): RedirectResponse
    {
        if ($lead->candidate) {
            return back()->withErrors(['discard' => 'Este lead ya es candidato. Descártalo desde su perfil de candidato.']);
        }

        if ($lead->status === LeadStatus::DISCARDED) {
            return to_route('admin.leads')->with('success', 'Este lead ya estaba descartado.');
        }

        $previous = $lead->status?->value ?: LeadStatus::NEW->value;
        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $lead->update(['status' => LeadStatus::DISCARDED, 'discard_reason' => $validated['reason']]);
        $activity = LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => $request->user()->id,
            'type' => 'discarded',
            'description' => 'Lead descartado: ' . $validated['reason'],
            'metadata' => ['reason' => $validated['reason']],
        ]);
        $this->publishLeadEvent($lead->fresh('candidate'), 'lead.discarded', ['activity' => $activity->only(['id', 'description', 'created_at']), 'notification' => ['title' => 'Lead descartado', 'description' => "{$lead->full_name} fue descartado."]]);
        $discardMail->send($lead->fresh('candidate'), $previous);

        return to_route('admin.leads')->with('success', 'Lead descartado correctamente.');
    }

    public function convert(Lead $lead, ConvertLeadToCandidate $convert): RedirectResponse
    {
        $candidate = $convert->handle($lead);
        $activity = $lead->activities()->latest('id')->first();
        $leadId = $lead->id;
        $candidateId = $candidate->id;
        $activityData = $activity?->only(['id', 'description', 'created_at']);
        $leadName = $lead->full_name;
        $candidateCode = $candidate->code;

        app()->terminating(function () use ($leadId, $candidateId, $activityData, $leadName, $candidateCode): void {
            try {
                $eventLead = Lead::query()->with('candidate')->find($leadId);
                if (! $eventLead) return;

                app(\App\Services\RealtimePublisher::class)->publishLead($eventLead, 'lead.converted', [
                    'candidate_id' => $candidateId,
                    'activity' => $activityData,
                    'notification' => ['title' => 'Lead convertido', 'description' => "{$leadName} pasó a {$candidateCode}."],
                ]);
            } catch (\Throwable $exception) {
                report($exception);
            }
        });

        return to_route('admin.leads.show', $lead)->with('success', "Lead convertido a {$candidate->code}.");
    }

    public function candidates(Request $request): Response
    {
        $query = Candidate::query()->with('lead')->latest();
        if ($search = $request->string('search')->trim()->value()) {
            $query->whereHas('lead', fn ($lead) => $lead->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }
        if ($type = $request->string('type')->value()) {
            $query->where('candidate_type', $type);

            // Modelos y monitores son directorios de personas admitidas;
            // las candidaturas en selección permanecen en "Candidatos".
            if (in_array($type, ['MODEL', 'MONITOR'], true)) {
                $query->whereIn('status', [
                    'ADMITTED',
                    'WAITING',
                    'ONBOARDING',
                    'CONTRACTING',
                    'INDUCTION',
                    'READY_TO_ACTIVATE',
                    'ACTIVE',
                ]);
            }
        }
        if ($status = $request->string('status')->value()) {
            $query->where('status', $status);
        }

        return Inertia::render('Admin/Recruitment/Candidates/Index', ['candidates' => $query->paginate(15)->withQueryString(), 'filters' => $request->only('search', 'type', 'status')]);
    }

    public function monitorModels(Request $request): Response
    {
        if ($this->monitorIsInTraining($request)) {
            $search = $request->string('search')->trim()->lower()->value();
            $models = TrainingRecord::query()->where('type', 'model')->whereNull('owner_user_id')->get()->map(fn (TrainingRecord $record) => $this->trainingModelPayload($record))->filter(function (array $model) use ($search) {
                if (! $search) return true;
                return Str::contains(Str::lower(implode(' ', [$model['lead']['first_name'], $model['lead']['last_name'], $model['lead']['email']])), $search);
            })->values();

            return Inertia::render('Admin/MonitorModels/Index', [
                'models' => ['data' => $models, 'total' => $models->count(), 'links' => []],
                'filters' => $request->only('search'),
                'trainingMode' => true,
            ]);
        }

        $query = Candidate::query()
            ->with('lead')
            ->where('candidate_type', CandidateType::MODEL->value)
            ->whereIn('status', [
                CandidateStatus::ADMITTED->value,
                CandidateStatus::WAITING->value,
                CandidateStatus::ONBOARDING->value,
                CandidateStatus::CONTRACTING->value,
                CandidateStatus::INDUCTION->value,
                CandidateStatus::READY_TO_ACTIVATE->value,
                CandidateStatus::ACTIVE->value,
            ])
            ->latest();

        if ($request->user()?->hasRole('monitor')) {
            $monitorCandidateId = Candidate::query()
                ->where('user_id', $request->user()->id)
                ->value('id');
            if (! $monitorCandidateId) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas('modelAssignments', fn ($assignment) => $assignment
                    ->where('monitor_candidate_id', $monitorCandidateId)
                    ->whereNull('ended_at'));
            }
        }

        if ($search = $request->string('search')->trim()->value()) {
            $query->whereHas('lead', fn ($lead) => $lead
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        return Inertia::render('Admin/MonitorModels/Index', [
            'models' => $query->paginate(15)->withQueryString(),
            'filters' => $request->only('search'),
        ]);
    }

    public function monitorModel(Request $request, int $candidate): Response
    {
        if ($this->monitorIsInTraining($request)) {
            $record = TrainingRecord::query()->where('type', 'model')->whereNull('owner_user_id')->findOrFail($candidate);

            return Inertia::render('Admin/MonitorModels/Show', [
                'model' => $this->trainingModelPayload($record),
                'trainingMode' => true,
            ]);
        }

        $candidate = Candidate::findOrFail($candidate);
        abort_unless(
            $candidate->candidate_type === CandidateType::MODEL
                && ! in_array($candidate->status, [CandidateStatus::DISCARDED, CandidateStatus::WITHDRAWN], true),
            404,
        );
        abort_unless(Gate::forUser($request->user())->allows('viewAssignedModel', $candidate), 403);

        return Inertia::render('Admin/MonitorModels/Show', [
            'model' => $candidate->load('lead'),
            'operationalProfile' => app(PortalRoomController::class)->modelProfileForMonitor($candidate),
            'statistics' => app(PortalRoomController::class)->statisticsForMonitor($candidate),
            'platformAccounts' => app(PortalRoomController::class)->platformAccountsForMonitor($candidate),
            'availablePlatformAccounts' => $request->user()->hasPermissionTo('studio_accounts.assign_assigned')
                ? $this->availablePlatformAccountsForMonitor()
                : [],
            'canManagePlatformAccounts' => $request->user()->hasPermissionTo('studio_accounts.assign_assigned'),
        ]);
    }

    private function availablePlatformAccountsForMonitor(): array
    {
        return StudioAccount::query()
            ->with('platform:id,name,is_active')
            ->where('status', '!=', 'DISABLED')
            ->whereHas('platform', fn ($query) => $query->where('is_active', true))
            ->orderBy('name')
            ->get(['id', 'platform_catalog_id', 'name', 'status', 'connection_mode'])
            ->map(fn (StudioAccount $account) => [
                'id' => $account->id,
                'name' => $account->name,
                'platform' => $account->platform?->name,
                'status' => $account->status,
                'connection_mode' => $account->connection_mode,
            ])
            ->values()
            ->all();
    }

    private function monitorIsInTraining(Request $request): bool
    {
        if (! $request->user()?->hasRole('monitor')) {
            return false;
        }

        $candidate = Candidate::query()
            ->where('user_id', $request->user()->id)
            ->first();

        return $candidate?->status !== CandidateStatus::ACTIVE;
    }

    private function trainingModelPayload(TrainingRecord $record): array
    {
        $payload = $record->payload;

        return [
            'id' => $record->id,
            'code' => $payload['code'] ?? $record->record_key,
            'status' => 'INDUCTION',
            'training' => true,
            'lead' => [
                'first_name' => $payload['first_name'] ?? $payload['name'] ?? 'Modelo',
                'last_name' => $payload['last_name'] ?? 'de prueba',
                'email' => $payload['email'] ?? 'modelo.prueba@thevelvet.test',
                'phone' => $payload['phone'] ?? '+57 300 000 0000',
                'city' => $payload['city'] ?? 'Bogotá',
                'country' => $payload['country'] ?? 'Colombia',
                'birth_date' => $payload['birth_date'] ?? '2000-01-15',
                'availability' => $payload['availability'] ?? 'Tiempo completo',
            ],
        ];
    }

    public function candidate(Request $request, Candidate $candidate): Response
    {
        $candidate->load([
            'lead.documents',
            'user',
            'activities.user',
            'interviews.slot',
            'contractAppointments.slot',
            'contractInvitations',
            'operationalProfile',
            'trainingRecords.completedBy',
            'modelAssignments' => fn ($query) => $query->latest('assigned_at')->with([
                'monitorCandidate.lead',
                'assignedBy',
                'endedBy',
            ]),
        ]);

        $canManageAssignments = $candidate->candidate_type === CandidateType::MODEL
            && $request->user()->hasPermissionTo('models.assign_monitor');

        return Inertia::render('Admin/Recruitment/Candidates/Show', [
            'candidate' => $candidate,
            'contracting' => [
                'available_slots' => ContractSlot::query()
                    ->where('status', 'AVAILABLE')
                    ->where('starts_at', '>', now('UTC'))
                    ->count(),
            ],
            'operational' => [
                'can_manage_training' => $request->user()->hasPermissionTo('onboarding.manage'),
                'can_manage_assignments' => $canManageAssignments,
                'can_end_assignments' => $candidate->candidate_type === CandidateType::MODEL
                    && $request->user()->hasPermissionTo('models.change_monitor'),
                'available_monitors' => $canManageAssignments
                    ? Candidate::query()
                        ->with('lead')
                        ->where('candidate_type', CandidateType::MONITOR->value)
                        ->where('status', CandidateStatus::ACTIVE->value)
                        ->orderBy('id')
                        ->get(['id', 'code', 'lead_id', 'status', 'candidate_type'])
                        ->map(fn (Candidate $monitor) => [
                            'id' => $monitor->id,
                            'code' => $monitor->code,
                            'name' => $monitor->lead?->full_name,
                        ])
                        ->values()
                    : [],
            ],
        ]);
    }

    public function startContracting(Request $request, Candidate $candidate): RedirectResponse
    {
        $candidate->loadMissing('lead');
        if ($candidate->status !== CandidateStatus::ADMITTED) {
            return back()->withErrors(['contracting' => 'El candidato debe estar admitido antes de iniciar la contratación.']);
        }

        $candidate->update(['status' => CandidateStatus::WAITING]);
        $activity = CandidateActivity::create([
            'candidate_id' => $candidate->id,
            'user_id' => $request->user()->id,
            'type' => 'contracting_started',
            'description' => 'Se inició la contratación y se enviaron los requisitos documentales. El candidato pasó a En espera.',
            'metadata' => ['from' => CandidateStatus::ADMITTED->value, 'to' => CandidateStatus::WAITING->value],
        ]);

        $this->publishLeadEvent($candidate->lead, 'candidate.status_changed', [
            'candidate_id' => $candidate->id,
            'status' => CandidateStatus::WAITING->value,
            'activity' => $activity->only(['id', 'description', 'created_at']),
            'notification' => ['title' => 'Contratación iniciada', 'description' => "Se enviaron los requisitos de contratación a {$candidate->lead->full_name}."],
        ]);

        try {
            $uploadUrl = URL::temporarySignedRoute('contracting.documents.show', now()->addDays(7), [
                'candidate' => $candidate->id,
                'token' => Str::random(40),
            ]);
            Mail::to($candidate->lead->email)->send(new ContractingRequirementsMail($candidate, $uploadUrl));
        } catch (\Throwable $exception) {
            report($exception);
            return back()->withErrors(['contracting' => 'El estado se actualizó, pero no fue posible enviar el correo. Revisa la configuración de correo.']);
        }

        return back()->with('success', 'Contratación iniciada. Se enviaron los requisitos al correo registrado.');
    }

    public function sendContractingInvitation(Request $request, Candidate $candidate): RedirectResponse
    {
        $candidate->loadMissing('lead');
        $service = app(ContractingInvitationService::class);

        if ($candidate->status !== CandidateStatus::WAITING) {
            return back()->withErrors(['contracting' => 'El candidato debe estar En espera para enviar la agenda.']);
        }

        if (! $service->hasVerifiedRequiredDocuments($candidate)) {
            return back()->withErrors(['contracting' => 'Primero deben estar aprobados los tres documentos obligatorios.']);
        }

        if (! $service->hasAvailableSlots()) {
            return back()->withErrors(['contracting' => 'Crea al menos una franja futura antes de enviar la agenda.']);
        }

        $invitation = $service->send($candidate, $request->user());
        if (! $invitation) {
            return back()->withErrors(['contracting' => 'La agenda ya fue enviada o no está disponible para este candidato.']);
        }

        $this->publishLeadEvent($candidate->fresh('lead')->lead, 'candidate.contracting_invited', [
            'candidate_id' => $candidate->id,
            'invitation_id' => $invitation->id,
            'notification' => ['title' => 'Agenda de contratación enviada', 'description' => "Se enviaron horarios de contratación a {$candidate->lead->full_name}."],
        ]);

        return back()->with('success', 'Agenda enviada al correo del candidato.');
    }

    public function activateCandidateAccess(Request $request, Candidate $candidate): RedirectResponse
    {
        [$candidate, $activity, $temporaryPassword] = DB::transaction(function () use ($candidate, $request): array {
            $candidate = Candidate::query()->with('lead')->lockForUpdate()->findOrFail($candidate->id);

            if ($candidate->status !== CandidateStatus::ONBOARDING) {
                throw ValidationException::withMessages(['access' => 'El candidato debe completar el onboarding antes de activar su acceso.']);
            }

            $roleSlug = $candidate->candidate_type === CandidateType::MONITOR ? 'monitor' : 'model';
            $temporaryPassword = Str::random(5) . '-' . Str::random(5);
            $user = $candidate->user_id
                ? User::query()->lockForUpdate()->findOrFail($candidate->user_id)
                : User::query()->where('email', $candidate->lead->email)->lockForUpdate()->first();

            if (! $candidate->user_id && $user) {
                throw ValidationException::withMessages(['access' => 'Ya existe una cuenta con el correo del candidato. Revísala antes de vincular el acceso.']);
            }

            if (! $user) {
                $user = User::create([
                    'name' => $candidate->lead->full_name,
                    'email' => $candidate->lead->email,
                    'password' => Hash::make($temporaryPassword),
                    'is_active' => true,
                ]);
            } else {
                $user->update([
                    'name' => $candidate->lead->full_name,
                    'email' => $candidate->lead->email,
                    'password' => Hash::make($temporaryPassword),
                    'is_active' => true,
                ]);
            }

            $user->syncRoles([$roleSlug]);
            $candidate->update([
                'user_id' => $user->id,
                'status' => CandidateStatus::INDUCTION,
            ]);

            OperationalProfile::updateOrCreate(
                ['candidate_id' => $candidate->id],
                [
                    'profile_type' => $candidate->candidate_type->value,
                    'operational_status' => 'PENDING',
                    'onboarding_completed_at' => now(),
                ],
            );

            OperationalTrainingRecord::firstOrCreate(
                ['candidate_id' => $candidate->id, 'kind' => 'ONBOARDING', 'title' => 'Onboarding administrativo'],
                [
                    'status' => 'COMPLETED',
                    'is_required' => true,
                    'completed_at' => now(),
                    'completed_by' => $request->user()->id,
                    'created_by' => $request->user()->id,
                    'notes' => 'Acceso operativo creado después de completar la contratación.',
                ],
            );

            OperationalTrainingRecord::firstOrCreate(
                ['candidate_id' => $candidate->id, 'kind' => 'TRAINING', 'title' => 'Inducción operativa inicial'],
                [
                    'status' => 'IN_PROGRESS',
                    'is_required' => true,
                    'created_by' => $request->user()->id,
                ],
            );

            $activity = CandidateActivity::create([
                'candidate_id' => $candidate->id,
                'user_id' => $request->user()->id,
                'type' => 'access_created',
                'description' => 'Acceso al dashboard creado. El candidato pasó a inducción operativa.',
                'metadata' => ['user_id' => $user->id, 'role' => $roleSlug],
            ]);

            return [$candidate->fresh(['lead', 'user']), $activity, $temporaryPassword];
        });

        // Didit supplies temporary HTTPS image links. Copy the verified portrait after
        // the user exists, outside the transaction, and keep it on the private disk.
        app(ProfilePhotoService::class)->importDefaultFromDidit($candidate);

        try {
            Mail::to($candidate->lead->email)->send(new CandidateAccessCredentialsMail($candidate, $temporaryPassword));
        } catch (\Throwable $exception) {
            report($exception);
            return back()->withErrors(['access' => 'El acceso fue creado, pero no fue posible enviar las credenciales. Revisa la configuración de correo.']);
        }

        $this->publishLeadEvent($candidate->lead, 'candidate.status_changed', [
            'candidate_id' => $candidate->id,
            'status' => $candidate->status->value,
            'access_created' => true,
            'activity' => $activity->only(['id', 'description', 'created_at']),
            'notification' => ['title' => 'Credenciales enviadas', 'description' => "Se enviaron las credenciales a {$candidate->lead->full_name}."],
        ]);

        return to_route('admin.candidates.show', $candidate)->with('success', 'Acceso creado. Se enviaron credenciales temporales y el perfil pasó a inducción operativa.');
    }

    public function updateCandidateAccessStatus(Request $request, Candidate $candidate): RedirectResponse
    {
        $validated = $request->validate(['active' => ['required', 'boolean']]);
        $candidate->loadMissing(['lead', 'user']);

        if ($candidate->status !== CandidateStatus::ACTIVE || ! $candidate->user) {
            return back()->withErrors(['access' => 'Solo puedes cambiar el acceso de un perfil activo con cuenta creada.']);
        }

        $active = (bool) $validated['active'];
        $candidate->user->update(['is_active' => $active]);
        app(AuditLogger::class)->record('access.user_status_changed', $candidate->user, ['candidate_id' => $candidate->id, 'is_active' => $active]);
        OperationalProfile::updateOrCreate(
            ['candidate_id' => $candidate->id],
            [
                'profile_type' => $candidate->candidate_type->value,
                'operational_status' => $active ? 'ACTIVE' : 'SUSPENDED',
            ],
        );
        $activity = CandidateActivity::create([
            'candidate_id' => $candidate->id,
            'user_id' => $request->user()->id,
            'type' => $active ? 'access_reactivated' : 'access_deactivated',
            'description' => $active ? 'El acceso operativo del perfil fue reactivado por administración.' : 'El acceso operativo del perfil fue desactivado por administración.',
            'metadata' => ['user_id' => $candidate->user->id, 'active' => $active],
        ]);

        $this->publishLeadEvent($candidate->lead, 'candidate.access_status_changed', [
            'candidate_id' => $candidate->id,
            'active' => $active,
            'activity' => $activity->only(['id', 'description', 'created_at']),
            'notification' => ['title' => $active ? 'Perfil reactivado' : 'Perfil desactivado', 'description' => "El acceso de {$candidate->lead->full_name} fue " . ($active ? 'reactivado.' : 'desactivado.')],
        ]);

        return back()->with('success', $active ? 'Perfil reactivado correctamente.' : 'Perfil desactivado correctamente.');
    }

    public function uploadInterviewNotes(Request $request, Candidate $candidate): RedirectResponse
    {
        $validated = $request->validate([
            'interview_notes' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:10240'],
        ]);

        if ($candidate->status !== CandidateStatus::INTERVIEW) {
            throw ValidationException::withMessages(['interview_notes' => 'Las anotaciones solo se pueden cargar durante la etapa de entrevista.']);
        }

        $document = $validated['interview_notes'];
        $storedPath = $document->store('lead-documents/interviews', 'local');

        try {
            $activity = DB::transaction(function () use ($request, $candidate, $document, $storedPath): CandidateActivity {
                $candidate = Candidate::query()->lockForUpdate()->findOrFail($candidate->id);
                if ($candidate->status !== CandidateStatus::INTERVIEW) {
                    throw ValidationException::withMessages(['interview_notes' => 'El candidato ya no se encuentra en entrevista.']);
                }

                $savedDocument = LeadDocument::create([
                    'lead_id' => $candidate->lead_id,
                    'type' => 'interview_notes',
                    'original_name' => $document->getClientOriginalName(),
                    'path' => $storedPath,
                    'mime_type' => $document->getMimeType(),
                    'size' => $document->getSize(),
                    'status' => 'COMPLETED',
                ]);
                $candidate->update(['status' => CandidateStatus::EVALUATION]);

                return CandidateActivity::create([
                    'candidate_id' => $candidate->id,
                    'user_id' => $request->user()->id,
                    'type' => 'interview_notes_uploaded',
                    'description' => 'Anotaciones de entrevista cargadas. El candidato pasó automáticamente a evaluación.',
                    'metadata' => ['document_id' => $savedDocument->id, 'original_name' => $savedDocument->original_name],
                ]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($storedPath);
            throw $exception;
        }

        $candidate->load('lead');
        $this->publishLeadEvent($candidate->lead->fresh('candidate'), 'candidate.status_changed', [
            'candidate_id' => $candidate->id,
            'status' => CandidateStatus::EVALUATION->value,
            'document_uploaded' => true,
            'activity' => $activity->only(['id', 'description', 'created_at']),
            'notification' => ['title' => 'Anotaciones de entrevista cargadas', 'description' => "{$candidate->lead->full_name} pasó automáticamente a evaluación."],
        ]);

        return back()->with('success', 'Anotaciones cargadas. El candidato pasó a evaluación.');
    }

    public function downloadInterviewNotes(Request $request, Candidate $candidate)
    {
        $document = $this->interviewNotesDocument($candidate);

        return Storage::disk('local')->download($document->path, $document->original_name, ['Content-Type' => 'application/pdf']);
    }

    public function previewInterviewNotes(Request $request, Candidate $candidate)
    {
        $document = $this->interviewNotesDocument($candidate);
        $filename = addslashes($document->original_name ?: 'anotaciones-entrevista.pdf');

        return response()->file(Storage::disk('local')->path($document->path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }

    private function interviewNotesDocument(Candidate $candidate): LeadDocument
    {
        $document = $candidate->load('lead.documents')->lead->documents()->where('type', 'interview_notes')->latest()->firstOrFail();
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return $document;
    }

    public function updateCandidate(Request $request, Candidate $candidate): RedirectResponse
    {
        $validated = $request->validate($this->leadDataRules($request));
        $identityDataChanged = $this->identityDataChanged($validated, $candidate->lead);
        $candidate->lead->update(Arr::except($validated, ['candidate_type']));
        $candidate->update(array_merge(
            ['candidate_type' => $validated['candidate_type']],
            $identityDataChanged ? $this->identityResetAttributes() : [],
        ));
        $activity = CandidateActivity::create([
            'candidate_id' => $candidate->id,
            'user_id' => $request->user()->id,
            'type' => 'data_updated',
            'description' => 'Información del candidato actualizada por el equipo.',
            'metadata' => ['fields' => array_keys($validated), 'identity_verification_reset' => $identityDataChanged],
        ]);
        $candidate->load('lead');
        $this->publishLeadEvent($candidate->lead, 'candidate.updated', ['candidate_id' => $candidate->id, 'activity' => $activity->only(['id', 'description', 'created_at']), 'notification' => ['title' => 'Candidato actualizado', 'description' => "Se actualizaron los datos de {$candidate->lead->full_name}."]]);

        return back()->with('success', 'Información del candidato actualizada correctamente.');
    }

    public function discardCandidate(Request $request, Candidate $candidate, ApplicationDiscardMailService $discardMail): RedirectResponse
    {
        $candidate->loadMissing('lead');
        if ($candidate->status === CandidateStatus::DISCARDED) {
            return to_route('admin.candidates.show', $candidate)->with('success', 'Este candidato ya estaba descartado.');
        }

        $previous = $candidate->status?->value ?: CandidateStatus::NEW->value;
        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $candidate->update([
            'status' => CandidateStatus::DISCARDED,
            'discarded_at' => now(),
            'discard_reason' => $validated['reason'],
        ]);
        $activity = CandidateActivity::create([
            'candidate_id' => $candidate->id,
            'user_id' => $request->user()->id,
            'type' => 'discarded',
            'description' => 'Candidato descartado: ' . $validated['reason'],
            'metadata' => ['reason' => $validated['reason']],
        ]);
        $candidate->load('lead');
        $this->publishLeadEvent($candidate->lead, 'candidate.discarded', ['candidate_id' => $candidate->id, 'activity' => $activity->only(['id', 'description', 'created_at']), 'notification' => ['title' => 'Candidato descartado', 'description' => "{$candidate->lead->full_name} fue descartado."]]);
        $discardMail->send($candidate->lead, $previous);

        return to_route('admin.candidates.show', $candidate)->with('success', 'Candidato descartado correctamente.');
    }

    public function updateCandidateStatus(Request $request, Candidate $candidate, InterviewInvitationService $invitationService, ApplicationDiscardMailService $discardMail, AdmittedMailService $admittedMail): RedirectResponse
    {
        $candidate->loadMissing('user');
        $validated = $request->validate(['status' => ['required', Rule::in(array_column(CandidateStatus::cases(), 'value'))]]);
        $nextStatus = CandidateStatus::from($validated['status']);
        $previousStatus = $candidate->status;
        $discardTransition = $nextStatus === CandidateStatus::DISCARDED && $previousStatus !== CandidateStatus::DISCARDED;
        $interview = null;

        if ($nextStatus === CandidateStatus::DISCARDED && ! $request->filled('reason')) {
            return back()->withErrors(['status' => 'Usa la opción “Descartar” e indica el motivo.']);
        }

        if ($nextStatus === CandidateStatus::EVALUATION && $previousStatus === CandidateStatus::INTERVIEW && ! $candidate->lead->documents()->where('type', 'interview_notes')->exists()) {
            return back()->withErrors(['status' => 'Carga primero el PDF con las anotaciones de la entrevista.']);
        }

        if ($nextStatus === CandidateStatus::ADMITTED && ! $this->identityVerificationIsApproved($candidate)) {
            return back()->withErrors(['status' => 'La candidata debe tener la identidad verificada y la mayoría de edad confirmada antes de ser admitida.']);
        }

        if ($nextStatus === CandidateStatus::ADMITTED && $previousStatus !== CandidateStatus::EVALUATION) {
            return back()->withErrors(['status' => 'El candidato debe estar en evaluación antes de ser admitido.']);
        }

        if ($nextStatus === CandidateStatus::WAITING && $previousStatus === CandidateStatus::ADMITTED) {
            return back()->withErrors(['status' => 'Inicia la contratacion desde la accion recomendada para enviar los requisitos por correo.']);
        }

        if ($nextStatus === CandidateStatus::CONTRACTING && $previousStatus !== CandidateStatus::WAITING) {
            return back()->withErrors(['status' => 'El candidato debe estar admitido antes de iniciar la contratación.']);
        }

        if ($nextStatus === CandidateStatus::ONBOARDING && $previousStatus !== CandidateStatus::CONTRACTING) {
            return back()->withErrors(['status' => 'El candidato debe completar la contratación antes de pasar a onboarding.']);
        }

        if ($nextStatus === CandidateStatus::INDUCTION) {
            return back()->withErrors(['status' => 'Crea el acceso al dashboard desde onboarding para iniciar la inducción operativa.']);
        }

        if ($nextStatus === CandidateStatus::READY_TO_ACTIVATE) {
            if ($previousStatus !== CandidateStatus::INDUCTION) {
                return back()->withErrors(['status' => 'El candidato debe completar la inducción antes de quedar listo para activar.']);
            }

            $hasPendingRequiredTraining = $candidate->trainingRecords()
                ->where('is_required', true)
                ->whereNotIn('status', ['COMPLETED', 'WAIVED'])
                ->exists();
            if ($hasPendingRequiredTraining) {
                return back()->withErrors(['status' => 'Completa o exime los registros obligatorios de inducción antes de continuar.']);
            }
        }

        if ($nextStatus === CandidateStatus::ACTIVE && (! $candidate->user_id || ! $candidate->user?->is_active)) {
            return back()->withErrors(['status' => 'Activa primero el acceso al dashboard desde el panel administrativo.']);
        }

        if ($nextStatus === CandidateStatus::ACTIVE && $previousStatus !== CandidateStatus::READY_TO_ACTIVATE) {
            return back()->withErrors(['status' => 'El candidato debe quedar listo para activar antes de habilitar la operación.']);
        }

        if ($previousStatus === $nextStatus) {
            return back();
        }

        if ($nextStatus === CandidateStatus::INTERVIEW) {
            try {
                $interview = $invitationService->send($candidate, $request->user(), false);
            } catch (\Throwable $exception) {
                report($exception);

                return back()->withErrors(['status' => 'No se pudo enviar el formulario de horarios. Revisa la configuración de correo.']);
            }

            if (! $interview) {
                return back()->withErrors(['status' => 'Genera al menos una franja disponible antes de pasar el candidato a entrevista.']);
            }
        }

        $attributes = ['status' => $nextStatus];
        if ($nextStatus === CandidateStatus::ADMITTED && ! $candidate->admitted_at) {
            $attributes['admitted_at'] = now();
        }
        if ($nextStatus === CandidateStatus::ACTIVE && ! $candidate->activated_at) {
            $attributes['activated_at'] = now();
        }
        if ($nextStatus === CandidateStatus::DISCARDED && ! $candidate->discarded_at) {
            $attributes['discarded_at'] = now();
        }
        if ($discardTransition) {
            $attributes['discard_reason'] = $request->string('reason')->trim()->value();
        }
        $candidate->update($attributes);

        if ($nextStatus === CandidateStatus::READY_TO_ACTIVATE) {
            OperationalProfile::updateOrCreate(
                ['candidate_id' => $candidate->id],
                [
                    'profile_type' => $candidate->candidate_type->value,
                    'operational_status' => 'PENDING',
                    'induction_completed_at' => now(),
                ],
            );
        }

        if ($nextStatus === CandidateStatus::ACTIVE) {
            OperationalProfile::updateOrCreate(
                ['candidate_id' => $candidate->id],
                [
                    'profile_type' => $candidate->candidate_type->value,
                    'operational_status' => 'ACTIVE',
                    'activated_at' => now(),
                    'activated_by' => $request->user()->id,
                ],
            );
        }
        $activity = CandidateActivity::create([
            'candidate_id' => $candidate->id,
            'user_id' => $request->user()->id,
            'type' => 'status_changed',
            'description' => "Estado actualizado a {$nextStatus->label()}.",
            'metadata' => ['from' => $previousStatus?->value, 'to' => $nextStatus->value],
        ]);
        $candidate->load('lead');
        $this->publishLeadEvent($candidate->lead, 'candidate.status_changed', [
            'candidate_id' => $candidate->id,
            'activity' => $activity->only(['id', 'description', 'created_at']),
            'activities' => collect([$interview?->getRelation('invitationActivity'), $activity])
                ->filter()
                ->map(fn (CandidateActivity $item) => $item->only(['id', 'description', 'created_at']))
                ->values()
                ->all(),
            'notification' => ['title' => 'Estado de candidato actualizado', 'description' => "{$candidate->lead->full_name}: {$nextStatus->label()}."],
        ]);

        if ($discardTransition) {
            $discardMail->send($candidate->lead, $previousStatus?->value ?: CandidateStatus::NEW->value);
        }

        if ($nextStatus === CandidateStatus::ADMITTED && $previousStatus !== CandidateStatus::ADMITTED) {
            $admittedMail->send($candidate->lead);
        }

        if ($nextStatus === CandidateStatus::ACTIVE && $previousStatus !== CandidateStatus::ACTIVE) {
            try {
                Mail::to($candidate->lead->email)->send(new CandidateActivatedMail($candidate));
            } catch (\Throwable $exception) {
                report($exception);
                return back()->withErrors(['status' => 'El perfil fue activado, pero no fue posible enviar el correo de confirmación. Revisa la configuración de correo.']);
            }
        }

        return back()->with('success', "Estado actualizado a {$nextStatus->label()}.");
    }

    private function localizeActivityDescription(?string $description): ?string
    {
        if (!$description) {
            return $description;
        }

        $labels = [
            'NEW' => 'Nueva',
            'CONTACTED' => 'Contactada',
            'QUALIFIED' => 'Calificada',
            'UNRESPONSIVE' => 'Sin respuesta',
            'CONVERTED' => 'Convertida',
            'DISCARDED' => 'Descartada',
            'PREQUALIFIED' => 'Precalificado',
            'INTERVIEW' => 'Entrevista',
            'EVALUATION' => 'Evaluación',
            'ADMITTED' => 'Admitido',
            'WAITING' => 'En espera',
            'ONBOARDING' => 'Onboarding',
            'CONTRACTING' => 'Contratación',
            'INDUCTION' => 'Inducción',
            'READY_TO_ACTIVATE' => 'Lista para activar',
            'ACTIVE' => 'Activa',
            'WITHDRAWN' => 'Retirada',
        ];

        return preg_replace_callback('/\\b[A-Z][A-Z_]+\\b/', fn (array $match) => $labels[$match[0]] ?? $match[0], $description);
    }

    private function leadDataRules(Request $request): array
    {
        return [
            'candidate_type' => ['required', Rule::in(['MODEL', 'MONITOR'])],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'sex' => ['required', Rule::in(['WOMAN', 'MAN'])],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['required', 'email', 'max:255'],
            'country' => ['sometimes', 'required', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:100'],
            'birth_date' => ['nullable', 'required_if:candidate_type,MODEL', 'date', 'before_or_equal:' . now()->subYears(18)->toDateString()],
            'experience' => ['nullable', 'string', 'max:5000'],
            'experience_years' => [Rule::requiredIf(fn () => $request->input('candidate_type') === 'MONITOR'), 'nullable', Rule::in(['1', '2', '3_PLUS'])],
            'speaks_english' => ['sometimes', 'boolean'],
            'english_level' => [Rule::requiredIf(fn () => $request->boolean('speaks_english')), 'nullable', Rule::in(['A1', 'A2', 'B1', 'B2', 'C1', 'C2', 'NATIVE'])],
            'motivation' => ['nullable', 'string', 'max:5000'],
            'availability' => ['required', 'string', 'max:100'],
            'work_mode' => ['required', 'string', 'max:100', 'in:En estudio'],
            'source' => ['nullable', 'string', 'max:100'],
        ];
    }

    private function identityDataChanged(array $validated, Lead $lead): bool
    {
        foreach (['first_name', 'last_name', 'birth_date'] as $field) {
            $current = $field === 'birth_date'
                ? $lead->birth_date?->toDateString()
                : $lead->getAttribute($field);

            if ((string) $current !== (string) ($validated[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }

    private function identityResetAttributes(): array
    {
        return [
            'identity_verification_status' => 'NOT_STARTED',
            'identity_verification_data' => null,
            'identity_verification_failure_reason' => null,
            'identity_verified_at' => null,
            'identity_verification_url' => null,
            'didit_session_id' => null,
        ];
    }

    public function sendPrequalification(Request $request, Candidate $candidate): RedirectResponse
    {
        $candidate->loadMissing('lead');

        if ($candidate->prequalification_completed_at || in_array($candidate->status, [CandidateStatus::PREQUALIFIED, CandidateStatus::ADMITTED, CandidateStatus::WAITING, CandidateStatus::DISCARDED, CandidateStatus::ONBOARDING, CandidateStatus::CONTRACTING, CandidateStatus::INDUCTION, CandidateStatus::READY_TO_ACTIVATE, CandidateStatus::ACTIVE, CandidateStatus::WITHDRAWN], true)) {
            return back()->withErrors(['prequalification' => 'Esta candidata ya completó la precalificación o avanzó a una etapa posterior.']);
        }

        $token = Str::random(64);
        $expiresAt = now()->addDays(7);
        $formUrl = URL::temporarySignedRoute('prequalification.show', $expiresAt, ['candidate' => $candidate->id, 'token' => $token]);

        try {
            Mail::to($candidate->lead->email)->send(new PrequalificationFormMail($candidate, $formUrl));
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors(['prequalification' => 'No se pudo enviar el formulario. Revisa la configuración del correo e inténtalo de nuevo.']);
        }

        $candidate->update([
            'status' => $candidate->status === CandidateStatus::NEW ? CandidateStatus::CONTACTED : $candidate->status,
            'prequalification_token_hash' => hash('sha256', $token),
            'prequalification_sent_at' => now(),
            'prequalification_expires_at' => $expiresAt,
        ]);
        $activity = CandidateActivity::create([
            'candidate_id' => $candidate->id,
            'user_id' => $request->user()->id,
            'type' => 'prequalification_sent',
            'description' => 'Formulario de precalificación enviado al correo registrado.',
            'metadata' => ['expires_at' => $expiresAt->toIso8601String()],
        ]);
        $this->publishLeadEvent($candidate->lead, 'candidate.prequalification_sent', ['candidate_id' => $candidate->id, 'activity' => $activity->only(['id', 'description', 'created_at']), 'notification' => ['title' => 'Precalificación enviada', 'description' => "Se envió el formulario a {$candidate->lead->full_name}."]]);

        return back()->with('success', 'Formulario de precalificación enviado correctamente.');
    }

    private function validatePrequalificationLink(Candidate $candidate, string $token): void
    {
        abort_unless($candidate->prequalification_token_hash && hash_equals($candidate->prequalification_token_hash, hash('sha256', $token)), 403, 'Este enlace no es válido.');
        abort_if($candidate->prequalification_expires_at?->isPast(), 410, 'Este enlace ya expiró.');
        abort_if($candidate->prequalification_completed_at, 409, 'Este formulario ya fue completado.');
    }

    private function diditIsConfigured(): bool
    {
        // Los tests funcionales usan el flujo de revisión manual con un archivo
        // simulado; la integración externa no debe activarse por las variables
        // del .env local del desarrollador.
        return ! app()->environment('testing')
            && filled(config('services.didit.api_key'))
            && filled(config('services.didit.workflow_id'));
    }

    private function identityVerificationIsApproved(Candidate $candidate): bool
    {
        return $candidate->identity_verification_status === 'APPROVED'
            && (app()->environment('local') || data_get($candidate->identity_verification_data ?? [], 'age_verified') === true);
    }

    private function diditSignatureIsValid(Request $request, string $rawBody, string $secret): bool
    {
        $signatureV2 = $request->header('X-Signature-V2');
        if ($signatureV2) {
            $provided = preg_replace('/^sha256=/i', '', trim($signatureV2));
            $payload = json_decode($rawBody, true);
            if (is_array($payload)) {
                $expectedCanonical = hash_hmac('sha256', $this->canonicalJson($payload), $secret);
                if (hash_equals($expectedCanonical, $provided)) {
                    return true;
                }
            }

            // Keep compatibility with Didit V2 implementations that sign raw JSON.
            return hash_equals(hash_hmac('sha256', $rawBody, $secret), $provided);
        }

        $signature = $request->header('X-Signature');
        if (! $signature) {
            return false;
        }
        $provided = preg_replace('/^sha256=/i', '', trim($signature));
        $timestamp = $request->header('X-Timestamp');
        $candidates = [$rawBody, $timestamp ? "{$timestamp}.{$rawBody}" : null];

        foreach (array_filter($candidates) as $signedBody) {
            if (hash_equals(hash_hmac('sha256', $signedBody, $secret), $provided)) {
                return true;
            }
        }

        return false;
    }

    private function canonicalJson(mixed $value): string
    {
        if (is_array($value)) {
            if (array_is_list($value)) {
                return '[' . implode(',', array_map(fn ($item) => $this->canonicalJson($item), $value)) . ']';
            }

            $keys = array_keys($value);
            sort($keys, SORT_STRING);
            $pairs = [];
            foreach ($keys as $key) {
                $pairs[] = json_encode((string) $key, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ':' . $this->canonicalJson($value[$key]);
            }

            return '{' . implode(',', $pairs) . '}';
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }

    private function diditIdentitySummary(array $payload, Candidate $candidate, string $status): array
    {
        $dateOfBirth = $this->findNestedValue($payload, ['date_of_birth', 'birth_date', 'dateOfBirth', 'dob']);
        $dateOfBirth = is_string($dateOfBirth) ? $this->normalizeDate($dateOfBirth) : null;
        $onboardingBirthDate = $candidate->lead?->birth_date?->toDateString();
        $ageVerified = false;

        if ($dateOfBirth) {
            try {
                $ageVerified = Carbon::parse($dateOfBirth)->isPast()
                    && Carbon::parse($dateOfBirth)->age >= 18
                    && (! $onboardingBirthDate || $dateOfBirth === $onboardingBirthDate);
            } catch (\Throwable) {
                $ageVerified = false;
            }
        }

        $documentName = $this->findNestedValue($payload, ['full_name', 'name']);
        $nameVerified = ! $documentName || $this->namesMatch(
            $candidate->lead?->first_name . ' ' . $candidate->lead?->last_name,
            (string) $documentName,
        );

        return [
            'provider' => 'didit',
            'provider_status' => $status,
            'name' => $documentName ?: $this->findNestedValue($payload, ['given_name']),
            'date_of_birth' => $dateOfBirth,
            'age_verified' => $ageVerified,
            'name_verified' => $nameVerified,
            'document_status' => $this->findNestedValue($payload, ['id_verification', 'id_verifications', 'document_status']),
            'liveness_status' => $this->findNestedValue($payload, ['liveness', 'liveness_checks']),
            'face_match_status' => $this->findNestedValue($payload, ['face_match', 'face_matches']),
            'images' => $this->diditImageSummary($payload),
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Didit report image URLs are temporary and may be absent depending on the workflow.
     * Keep only HTTPS URLs and the image types useful to authorized reviewers.
     */
    private function diditImageSummary(array $payload): array
    {
        $images = [];
        $allowed = [
            'front_image' => 'Documento (frente)',
            'full_front_image' => 'Documento completo (frente)',
            'back_image' => 'Documento (reverso)',
            'full_back_image' => 'Documento completo (reverso)',
            'portrait_image' => 'Retrato del documento',
            'selfie_image' => 'Selfie',
            'face_image' => 'Imagen facial',
        ];

        $walk = function (mixed $value) use (&$walk, &$images, $allowed): void {
            if (! is_array($value)) {
                return;
            }

            foreach ($value as $key => $child) {
                $normalizedKey = Str::of((string) $key)->lower()->replace(['-', ' '], '_')->toString();
                if (isset($allowed[$normalizedKey]) && is_string($child) && filter_var($child, FILTER_VALIDATE_URL) && Str::startsWith($child, 'https://')) {
                    $images[$normalizedKey] = ['label' => $allowed[$normalizedKey], 'url' => $child];
                    continue;
                }
                $walk($child);
            }
        };

        $walk($payload);

        return array_values($images);
    }

    private function namesMatch(string $expected, string $actual): bool
    {
        $normalize = static function (string $value): array {
            $value = Str::lower(Str::ascii($value));
            $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?: '';

            return array_values(array_filter(explode(' ', trim($value))));
        };

        $expectedTokens = $normalize($expected);
        $actualTokens = $normalize($actual);

        return $expectedTokens !== [] && $actualTokens !== []
            && count(array_diff($expectedTokens, $actualTokens)) === 0;
    }

    private function findNestedValue(mixed $value, array $keys): mixed
    {
        if (! is_array($value)) {
            return null;
        }

        foreach ($keys as $key) {
            if (array_key_exists($key, $value)) {
                $found = $value[$key];
                if (is_array($found) && array_is_list($found)) {
                    return $found[0] ?? null;
                }

                return is_array($found) ? ($found['status'] ?? $found['value'] ?? null) : $found;
            }
        }

        foreach ($value as $child) {
            $found = $this->findNestedValue($child, $keys);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    private function normalizeDate(string $value): ?string
    {
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
