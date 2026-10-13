<?php

namespace App\Http\Controllers;

use App\Enums\CandidateStatus;
use App\Mail\ContractingScheduledMail;
use App\Models\Candidate;
use App\Models\CandidateActivity;
use App\Models\ContractAppointment;
use App\Models\ContractInvitation;
use App\Models\ContractSlot;
use App\Models\LeadDocument;
use App\Services\RealtimePublisher;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ContractingController extends Controller
{
    private const TIMEZONE = 'America/Bogota';

    public function booking(ContractInvitation $invitation, string $token): Response
    {
        $this->validateContractInvitation($invitation, $token);
        $slots = ContractSlot::query()->where('status', 'AVAILABLE')->where('starts_at', '>', now('UTC'))->orderBy('starts_at')->get()->map(fn (ContractSlot $slot) => $this->formatSlot($slot));
        $invitation->load('candidate.lead');

        return Inertia::render('Public/ContractingBooking', [
            'candidate' => ['name' => $invitation->candidate->lead->full_name, 'code' => $invitation->candidate->code],
            'slots' => $slots,
            'expiresAt' => $invitation->invitation_expires_at,
            'actionUrl' => request()->fullUrl(),
        ]);
    }

    public function bookingSuccess(Request $request): Response
    {
        return Inertia::render('Public/ContractingBookingSuccess', [
            'candidate' => $request->session()->get('contract_booking_completed.candidate'),
            'scheduledAt' => $request->session()->get('contract_booking_completed.scheduled_at'),
        ]);
    }

    public function bookPublic(Request $request, ContractInvitation $invitation, string $token): RedirectResponse
    {
        $this->validateContractInvitation($invitation, $token);
        $validated = $request->validate(['slot_id' => ['required', 'integer', 'exists:contract_slots,id']]);

        $appointment = DB::transaction(function () use ($invitation, $validated): ContractAppointment {
            $invitation = ContractInvitation::query()->with('candidate.lead')->lockForUpdate()->findOrFail($invitation->id);
            $this->validateContractInvitation($invitation, request()->route('token'));
            $slot = ContractSlot::query()->lockForUpdate()->findOrFail($validated['slot_id']);
            $slotStart = Carbon::createFromFormat('Y-m-d H:i:s', $slot->getRawOriginal('starts_at'), 'UTC');
            abort_if($slot->status !== 'AVAILABLE' || $slotStart->isPast(), 409, 'Este horario ya no está disponible. Elige otro.');

            $slot->update(['status' => 'BOOKED']);
            $appointment = ContractAppointment::create([
                'candidate_id' => $invitation->candidate_id,
                'contract_slot_id' => $slot->id,
                'created_by' => null,
                'status' => 'SCHEDULED',
            ]);
            $invitation->update(['status' => 'USED', 'used_at' => now()]);
            $invitation->candidate->update(['status' => CandidateStatus::CONTRACTING]);
            CandidateActivity::create([
                'candidate_id' => $invitation->candidate_id,
                'user_id' => null,
                'type' => 'contracting_scheduled',
                'description' => 'La candidata agendó su contratación desde el enlace enviado por correo.',
                'metadata' => ['appointment_id' => $appointment->id, 'slot_id' => $slot->id],
            ]);

            return $appointment->fresh(['candidate.lead', 'slot']);
        });

        $activity = $appointment->candidate->activities()->latest('id')->first();
        app(RealtimePublisher::class)->publishLead($appointment->candidate->lead, 'candidate.status_changed', [
            'candidate_id' => $appointment->candidate_id,
            'status' => CandidateStatus::CONTRACTING->value,
            'appointment' => ['id' => $appointment->id, 'starts_at' => $appointment->slot?->starts_at?->toIso8601String(), 'ends_at' => $appointment->slot?->ends_at?->toIso8601String()],
            'activity' => $activity?->only(['id', 'description', 'created_at']),
            'notification' => ['title' => 'Contratación agendada', 'description' => "{$appointment->candidate->lead->full_name} agendó su contratación."],
        ]);
        try {
            Mail::to($appointment->candidate->lead->email)->send(new ContractingScheduledMail($appointment));
        } catch (\Throwable $exception) {
            report($exception);
        }

        return to_route('contracting.booking.success')->with('contract_booking_completed', [
            'candidate' => ['name' => $appointment->candidate->lead->full_name, 'code' => $appointment->candidate->code],
            'scheduled_at' => Carbon::createFromFormat('Y-m-d H:i:s', $appointment->slot->getRawOriginal('starts_at'), 'UTC')->setTimezone(self::TIMEZONE)->toIso8601String(),
        ]);
    }

    public function index(): Response
    {
        $slots = ContractSlot::query()->with('appointment.candidate.lead')
            ->where('starts_at', '>=', now('UTC'))
            ->orderBy('starts_at')->limit(250)->get()
            ->map(fn (ContractSlot $slot) => $this->formatSlot($slot));

        $candidates = Candidate::query()->with('lead')
            ->whereIn('status', [CandidateStatus::WAITING, CandidateStatus::CONTRACTING])
            ->whereHas('lead')
            ->whereDoesntHave('contractAppointments', fn ($query) => $query->whereIn('status', ['SCHEDULED']))
            ->latest()->get();

        $appointments = ContractAppointment::query()->with('candidate.lead', 'slot')
            ->whereIn('status', ['SCHEDULED', 'COMPLETED'])
            ->latest()->limit(100)->get()
            ->map(function (ContractAppointment $appointment) {
                $data = $appointment->toArray();
                $data['slot'] = $appointment->slot ? $this->formatSlot($appointment->slot) : null;
                return $data;
            });

        return Inertia::render('Admin/Recruitment/Contracting/Index', compact('slots', 'candidates', 'appointments'));
    }

    public function generateSlots(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'from_date' => ['required', 'date', 'after_or_equal:today'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'weekdays' => ['required', 'array', 'min:1'],
            'weekdays.*' => ['integer', 'between:1,7'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ]);

        $from = Carbon::parse($validated['from_date'], self::TIMEZONE)->startOfDay();
        $to = Carbon::parse($validated['to_date'], self::TIMEZONE)->startOfDay();
        if ($from->diffInDays($to) > 90) {
            return back()->withErrors(['to_date' => 'El rango máximo para generar horarios es de 90 días.']);
        }

        $created = 0;
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            if (in_array($cursor->dayOfWeekIso, array_map('intval', $validated['weekdays']), true)) {
                $start = Carbon::createFromFormat('Y-m-d H:i', $cursor->format('Y-m-d') . ' ' . $validated['start_time'], self::TIMEZONE);
                $end = Carbon::createFromFormat('Y-m-d H:i', $cursor->format('Y-m-d') . ' ' . $validated['end_time'], self::TIMEZONE);
                for ($slotStart = $start->copy(); $slotStart->lt($end); $slotStart->addHour()) {
                    $slotEnd = $slotStart->copy()->addHour();
                    if ($slotStart->isFuture() && ! ContractSlot::where('starts_at', $slotStart->utc())->where('ends_at', $slotEnd->utc())->exists()) {
                        ContractSlot::create(['starts_at' => $slotStart->utc(), 'ends_at' => $slotEnd->utc(), 'created_by' => $request->user()->id]);
                        $created++;
                    }
                }
            }
            $cursor->addDay();
        }

        if ($created) {
            app(RealtimePublisher::class)->publish('contract.slots_generated', ['notification' => ['title' => 'Franjas de contratación', 'description' => "Se generaron {$created} franjas para contratación."]]);
        }

        return back()->with('success', $created ? "Se generaron {$created} franjas de contratación." : 'No había franjas nuevas para generar.');
    }

    public function destroyAvailableSlots(): RedirectResponse
    {
        $deleted = ContractSlot::query()->where('status', 'AVAILABLE')->whereDoesntHave('appointment')->delete();
        return back()->with('success', $deleted ? "Se eliminaron {$deleted} franjas disponibles." : 'No había franjas disponibles para eliminar.');
    }

    public function book(Request $request): RedirectResponse
    {
        $validated = $request->validate(['candidate_id' => ['required', 'integer', 'exists:candidates,id'], 'slot_id' => ['required', 'integer', 'exists:contract_slots,id'], 'notes' => ['nullable', 'string', 'max:2000']]);

        [$appointment, $activity] = DB::transaction(function () use ($request, $validated): array {
            $candidate = Candidate::query()->with('lead')->lockForUpdate()->findOrFail($validated['candidate_id']);
            if (! in_array($candidate->status, [CandidateStatus::WAITING, CandidateStatus::CONTRACTING], true)) {
                throw ValidationException::withMessages(['candidate_id' => 'El candidato debe estar en espera o en contratación.']);
            }
            if ($candidate->contractAppointments()->where('status', 'SCHEDULED')->exists()) {
                throw ValidationException::withMessages(['candidate_id' => 'El candidato ya tiene una contratación programada.']);
            }

            $slot = ContractSlot::query()->lockForUpdate()->findOrFail($validated['slot_id']);
            abort_if($slot->status !== 'AVAILABLE' || $slot->starts_at->isPast(), 409, 'Esta franja ya no está disponible.');
            $slot->update(['status' => 'BOOKED']);
            $appointment = ContractAppointment::create(['candidate_id' => $candidate->id, 'contract_slot_id' => $slot->id, 'created_by' => $request->user()->id, 'notes' => $validated['notes'] ?? null]);
            if ($candidate->status !== CandidateStatus::CONTRACTING) {
                $candidate->update(['status' => CandidateStatus::CONTRACTING]);
            }
            $activity = CandidateActivity::create(['candidate_id' => $candidate->id, 'user_id' => $request->user()->id, 'type' => 'contracting_scheduled', 'description' => 'Contratación física programada. El candidato pasó a En contratación.', 'metadata' => ['appointment_id' => $appointment->id, 'slot_id' => $slot->id]]);
            return [$appointment->fresh(['candidate.lead', 'slot']), $activity];
        });

        app(RealtimePublisher::class)->publishLead($appointment->candidate->lead, 'candidate.status_changed', ['candidate_id' => $appointment->candidate_id, 'status' => CandidateStatus::CONTRACTING->value, 'activity' => $activity->only(['id', 'description', 'created_at']), 'notification' => ['title' => 'Contratación programada', 'description' => "Se programó la contratación de {$appointment->candidate->lead->full_name}."]]);
        try {
            Mail::to($appointment->candidate->lead->email)->send(new ContractingScheduledMail($appointment));
        } catch (\Throwable $exception) {
            report($exception);
        }
        return back()->with('success', 'Contratación programada y candidato movido a En contratación.');
    }

    public function reschedule(Request $request, ContractAppointment $appointment): RedirectResponse
    {
        $validated = $request->validate([
            'slot_id' => ['required', 'integer', 'exists:contract_slots,id'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        [$appointment, $activity] = DB::transaction(function () use ($request, $appointment, $validated): array {
            $appointment = ContractAppointment::query()->with('candidate.lead', 'slot')->lockForUpdate()->findOrFail($appointment->id);
            if ($appointment->status !== 'SCHEDULED' || $appointment->candidate->status !== CandidateStatus::CONTRACTING) {
                throw ValidationException::withMessages(['appointment' => 'Solo se puede reprogramar una contratación activa.']);
            }
            if ((int) $appointment->contract_slot_id === (int) $validated['slot_id']) {
                throw ValidationException::withMessages(['slot_id' => 'Selecciona una franja diferente a la actual.']);
            }

            $newSlot = ContractSlot::query()->lockForUpdate()->findOrFail($validated['slot_id']);
            abort_if($newSlot->status !== 'AVAILABLE' || $newSlot->starts_at->isPast(), 409, 'La nueva franja ya no está disponible.');
            $oldSlotId = $appointment->contract_slot_id;
            $oldStartsAt = $appointment->slot?->starts_at?->toIso8601String();
            $appointment->slot?->update(['status' => 'AVAILABLE']);
            $newSlot->update(['status' => 'BOOKED']);
            $appointment->update(['contract_slot_id' => $newSlot->id]);
            $activity = CandidateActivity::create([
                'candidate_id' => $appointment->candidate_id,
                'user_id' => $request->user()->id,
                'type' => 'contracting_rescheduled',
                'description' => 'La contratación física fue reprogramada. Motivo: ' . $validated['reason'],
                'metadata' => ['appointment_id' => $appointment->id, 'old_slot_id' => $oldSlotId, 'new_slot_id' => $newSlot->id, 'reason' => $validated['reason'], 'old_starts_at' => $oldStartsAt],
            ]);

            return [$appointment->fresh(['candidate.lead', 'slot']), $activity];
        });

        app(RealtimePublisher::class)->publishLead($appointment->candidate->lead, 'candidate.updated', ['candidate_id' => $appointment->candidate_id, 'appointment_id' => $appointment->id, 'activity' => $activity->only(['id', 'description', 'created_at']), 'notification' => ['title' => 'Contratación reprogramada', 'description' => "Se reprogramó la contratación de {$appointment->candidate->lead->full_name}."]]);
        try {
            Mail::to($appointment->candidate->lead->email)->send(new ContractingScheduledMail($appointment));
        } catch (\Throwable $exception) {
            report($exception);
        }

        return back()->with('success', 'Contratación reprogramada y nueva confirmación enviada.');
    }

    public function complete(Request $request, ContractAppointment $appointment): RedirectResponse
    {
        [$appointment, $activity] = DB::transaction(function () use ($request, $appointment): array {
            $appointment = ContractAppointment::query()->with('candidate.lead', 'slot')->lockForUpdate()->findOrFail($appointment->id);
            if ($appointment->status !== 'SCHEDULED' || $appointment->candidate->status !== CandidateStatus::CONTRACTING) {
                throw ValidationException::withMessages(['appointment' => 'Solo se puede finalizar una contratación programada.']);
            }
            $appointment->update(['status' => 'COMPLETED', 'completed_at' => now()]);
            $appointment->candidate->update(['status' => CandidateStatus::ONBOARDING, 'contracted_at' => $appointment->candidate->contracted_at ?: now()]);
            $activity = CandidateActivity::create(['candidate_id' => $appointment->candidate_id, 'user_id' => $request->user()->id, 'type' => 'contracting_completed', 'description' => 'Contratación finalizada. El candidato pasó a En onboarding.', 'metadata' => ['appointment_id' => $appointment->id]]);
            return [$appointment->fresh(['candidate.lead', 'slot']), $activity];
        });

        app(RealtimePublisher::class)->publishLead($appointment->candidate->lead, 'candidate.status_changed', ['candidate_id' => $appointment->candidate_id, 'status' => CandidateStatus::ONBOARDING->value, 'activity' => $activity->only(['id', 'description', 'created_at']), 'notification' => ['title' => 'Contratación finalizada', 'description' => "{$appointment->candidate->lead->full_name} pasó a onboarding."]]);
        return back()->with('success', 'Contratación finalizada. El candidato pasó a En onboarding.');
    }

    public function publicDocuments(Request $request, Candidate $candidate): Response
    {
        abort_unless(in_array($candidate->status, [CandidateStatus::WAITING, CandidateStatus::CONTRACTING], true), 403, 'Este enlace ya no está disponible.');

        $candidate->loadMissing('lead.documents');
        $documentsByRequirement = $this->currentPublicDocuments($candidate);

        return Inertia::render('Public/ContractingDocuments', [
            'candidate' => ['id' => $candidate->id, 'name' => $candidate->lead->full_name, 'code' => $candidate->code],
            'requirements' => array_map(fn (array $requirement) => $this->publicRequirementPayload($candidate, $requirement, $documentsByRequirement->get($requirement['key'])), $this->publicDocumentRequirements()),
        ]);
    }

    public function publicDocumentsSuccess(Request $request, Candidate $candidate, string $token): Response
    {
        abort_unless(in_array($candidate->status, [CandidateStatus::WAITING, CandidateStatus::CONTRACTING], true), 403, 'Este enlace ya no está disponible.');

        $candidate->loadMissing('lead.documents');
        $documents = $this->currentPublicDocuments($candidate)->values()->map(fn (LeadDocument $document) => [
            'name' => $document->original_name,
            'size' => $document->size,
            'status' => $document->status,
            'uploaded_at' => $document->created_at?->toIso8601String(),
        ])->all();
        $publisher = app(RealtimePublisher::class);

        return Inertia::render('Public/ContractingDocumentsSuccess', [
            'candidate' => ['id' => $candidate->id, 'name' => $candidate->lead->full_name, 'code' => $candidate->code],
            'documents' => $documents,
            'realtime' => [
                'enabled' => (bool) config('services.ably.api_key'),
                'channel' => $publisher->candidateDocumentsChannel($candidate),
                'auth_url' => URL::temporarySignedRoute('contracting.documents.realtime-token', now()->addHour(), ['candidate' => $candidate, 'token' => $token]),
            ],
        ]);
    }

    public function publicRealtimeToken(Request $request, Candidate $candidate, string $token): JsonResponse
    {
        abort_unless(in_array($candidate->status, [CandidateStatus::WAITING, CandidateStatus::CONTRACTING], true), 403, 'Este enlace ya no está disponible.');
        $apiKey = config('services.ably.api_key');
        abort_unless($apiKey && str_contains($apiKey, ':'), 503, 'La sincronización en tiempo real no está disponible.');

        [$keyName, $keySecret] = explode(':', $apiKey, 2);
        $publisher = app(RealtimePublisher::class);
        $response = Http::withBasicAuth($keyName, $keySecret)->acceptJson()->post(
            'https://rest.ably.io/keys/' . rawurlencode($keyName) . '/requestToken',
            [
                'keyName' => $keyName,
                'capability' => json_encode([$publisher->candidateDocumentsChannel($candidate) => ['subscribe']]),
                'ttl' => 3600000,
            ]
        );

        abort_unless($response->successful(), 502, 'No fue posible activar la sincronización en tiempo real.');
        return response()->json($response->json());
    }

    public function storePublicDocuments(Request $request, Candidate $candidate, string $token): RedirectResponse
    {
        abort_unless(in_array($candidate->status, [CandidateStatus::WAITING, CandidateStatus::CONTRACTING], true), 403, 'Este enlace ya no está disponible.');

        $candidate->loadMissing('lead.documents');
        $requirements = $this->publicDocumentRequirements();
        $documentsByRequirement = $this->currentPublicDocuments($candidate);
        $finalize = $request->boolean('finalize');
        $rules = ['documents' => ['nullable', 'array', 'max:1'], 'finalize' => ['nullable', 'boolean']];

        foreach ($requirements as $requirement) {
            $rules["documents.{$requirement['key']}"] = [
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'mimetypes:application/pdf,image/jpeg,image/png',
                'max:10240',
            ];
        }

        $validated = $request->validate($rules);
        $submittedDocuments = $validated['documents'] ?? [];
        $requirementKeys = array_column($requirements, 'key');

        if (array_diff(array_keys($submittedDocuments), $requirementKeys)) {
            throw ValidationException::withMessages(['documents' => 'El documento indicado no pertenece a este proceso.']);
        }

        if (!$finalize && count($submittedDocuments) !== 1) {
            throw ValidationException::withMessages(['documents' => 'Selecciona un documento para guardar este paso.']);
        }

        foreach ($submittedDocuments as $key => $document) {
            $existing = $documentsByRequirement->get($key);
            if ($existing?->status === 'VERIFIED') {
                throw ValidationException::withMessages(["documents.{$key}" => 'Este documento ya fue aprobado y no puede reemplazarse.']);
            }
        }

        if ($finalize) {
            $missingRequirements = collect($requirements)
                ->filter(fn (array $requirement) => $requirement['required'])
                ->filter(function (array $requirement) use ($documentsByRequirement, $submittedDocuments) {
                    if (isset($submittedDocuments[$requirement['key']])) return false;
                    $document = $documentsByRequirement->get($requirement['key']);
                    return !$document || $document->status === 'REJECTED';
                });

            if ($missingRequirements->isNotEmpty()) {
                throw ValidationException::withMessages($missingRequirements->mapWithKeys(fn (array $requirement) => ["documents.{$requirement['key']}" => "Completa {$requirement['title']} antes de finalizar."])->all());
            }
        }

        $stored = [];
        try {
            foreach ($requirements as $requirement) {
                $document = $submittedDocuments[$requirement['key']] ?? null;
                if (!$document) {
                    continue;
                }

                $path = $document->store('lead-documents/contracts', 'local');
                $stored[] = $path;
                LeadDocument::create([
                    'lead_id' => $candidate->lead_id,
                    'type' => 'contract_document',
                    'original_name' => $document->getClientOriginalName(),
                    'path' => $path,
                    'mime_type' => $document->getMimeType(),
                    'size' => $document->getSize(),
                    'status' => 'PENDING',
                    'metadata' => [
                        'source' => 'candidate_upload',
                        'candidate_id' => $candidate->id,
                        'requirement_key' => $requirement['key'],
                        'requirement_title' => $requirement['title'],
                    ],
                ]);
            }
        } catch (\Throwable $exception) {
            foreach ($stored as $path) Storage::disk('local')->delete($path);
            throw $exception;
        }

        $activity = null;
        if (count($stored)) {
            $activity = CandidateActivity::create(['candidate_id' => $candidate->id, 'user_id' => null, 'type' => 'candidate_documents_uploaded', 'description' => 'El candidato cargó documentos de contratación mediante el enlace seguro.', 'metadata' => ['count' => count($stored), 'source' => 'signed_link', 'requirements' => array_keys($submittedDocuments)]]);
        }
        $candidate->load('lead');
        if ($finalize) {
            $publisher = app(RealtimePublisher::class);
            $publisher->publishLead($candidate->lead, 'candidate.updated', [
                'candidate_id' => $candidate->id,
                'activity' => $activity?->only(['id', 'description', 'created_at']),
                'notification' => ['title' => 'Documentos recibidos', 'description' => "{$candidate->lead->full_name} cargó documentos de contratación."],
            ]);
            $publisher->publish('candidate.documents_uploaded', [
                'channel' => $publisher->candidateDocumentsChannel($candidate),
                'candidate_id' => $candidate->id,
                'documents_count' => $candidate->lead->documents()->where('type', 'contract_document')->count(),
                'finalized' => true,
                'activity' => $activity?->only(['id', 'description', 'created_at']),
            ]);
            return redirect()->to(URL::temporarySignedRoute('contracting.documents.success', now()->addHour(), ['candidate' => $candidate, 'token' => $token]));
        }
        if ($activity) {
            app(RealtimePublisher::class)->publishLead($candidate->lead, 'candidate.updated', ['candidate_id' => $candidate->id, 'activity' => $activity->only(['id', 'description', 'created_at']), 'notification' => ['title' => 'Documentos recibidos', 'description' => "{$candidate->lead->full_name} cargó documentos de contratación."]]);
        }

        return back()->with('success', $finalize ? 'Documentación enviada correctamente. El equipo administrativo la revisará.' : 'Documento guardado. Continúa con el siguiente requisito.');
    }

    public function previewPublicDocument(Candidate $candidate, string $token, LeadDocument $document)
    {
        $this->ensurePublicDocumentBelongsToCandidate($candidate, $document);
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return response()->file(Storage::disk('local')->path($document->path), [
            'Content-Type' => $document->mime_type,
            'Content-Disposition' => 'inline; filename="' . addslashes($document->original_name) . '"',
        ]);
    }

    public function downloadPublicDocument(Candidate $candidate, string $token, LeadDocument $document)
    {
        $this->ensurePublicDocumentBelongsToCandidate($candidate, $document);
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->download($document->path, $document->original_name, ['Content-Type' => $document->mime_type]);
    }

    public function uploadDocuments(Request $request, Candidate $candidate): RedirectResponse
    {
        $validated = $request->validate([
            'documents' => ['required', 'array', 'min:1', 'max:5'],
            'documents.*' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:10240'],
        ]);
        if (! in_array($candidate->status, [CandidateStatus::CONTRACTING, CandidateStatus::ONBOARDING], true)) {
            throw ValidationException::withMessages(['documents' => 'Los documentos de contratación solo se cargan durante En contratación o En onboarding.']);
        }

        $stored = [];
        try {
            foreach ($validated['documents'] as $document) {
                $path = $document->store('lead-documents/contracts', 'local');
                $stored[] = $path;
                LeadDocument::create([
                    'lead_id' => $candidate->lead_id,
                    'type' => 'contract_document',
                    'original_name' => $document->getClientOriginalName(),
                    'path' => $path,
                    'mime_type' => $document->getMimeType(),
                    'size' => $document->getSize(),
                    'status' => 'COMPLETED',
                ]);
            }
        } catch (\Throwable $exception) {
            foreach ($stored as $path) Storage::disk('local')->delete($path);
            throw $exception;
        }

        $activity = CandidateActivity::create(['candidate_id' => $candidate->id, 'user_id' => $request->user()->id, 'type' => 'contract_documents_uploaded', 'description' => 'Contrato firmado cargado. El candidato pasó a En onboarding.', 'metadata' => ['count' => count($stored), 'next_status' => CandidateStatus::ONBOARDING->value]]);
        if ($candidate->status === CandidateStatus::CONTRACTING) {
            $candidate->update([
                'status' => CandidateStatus::ONBOARDING,
                'contracted_at' => $candidate->contracted_at ?: now(),
            ]);
        } elseif (! $candidate->contracted_at) {
            $candidate->update(['contracted_at' => now()]);
        }
        $candidate->load('lead');
        app(RealtimePublisher::class)->publishLead($candidate->lead, 'candidate.status_changed', [
            'candidate_id' => $candidate->id,
            'status' => CandidateStatus::ONBOARDING->value,
            'activity' => $activity->only(['id', 'description', 'created_at']),
            'notification' => [
                'title' => 'Contrato firmado cargado',
                'description' => "{$candidate->lead->full_name} pasó a onboarding.",
            ],
        ]);
        return back()->with('success', 'Contrato firmado cargado correctamente. El candidato pasó a onboarding.');
    }

    private function formatSlot(ContractSlot $slot): array
    {
        $startsAt = Carbon::createFromFormat('Y-m-d H:i:s', $slot->getRawOriginal('starts_at'), 'UTC')->setTimezone(self::TIMEZONE);
        $endsAt = Carbon::createFromFormat('Y-m-d H:i:s', $slot->getRawOriginal('ends_at'), 'UTC')->setTimezone(self::TIMEZONE);
        return array_merge($slot->toArray(), ['starts_at' => $startsAt->toIso8601String(), 'ends_at' => $endsAt->toIso8601String()]);
    }

    private function publicDocumentRequirements(): array
    {
        return [
            ['key' => 'identity_document', 'title' => 'Documento de identidad vigente', 'description' => 'Carga un documento legible y vigente en PDF, JPG o PNG.', 'required' => true],
            ['key' => 'bank_certificate', 'title' => 'Certificación bancaria', 'description' => 'Debe mostrar el titular y los datos de la cuenta.', 'required' => true],
            ['key' => 'tax_document', 'title' => 'Documento tributario', 'description' => 'Adjunta el RUT o el soporte tributario solicitado por el equipo.', 'required' => true],
            ['key' => 'additional_document', 'title' => 'Documento adicional', 'description' => 'Solo si el equipo te solicitó un soporte adicional.', 'required' => false],
        ];
    }

    private function currentPublicDocuments(Candidate $candidate)
    {
        return $candidate->lead->documents
            ->where('type', 'contract_document')
            ->filter(fn (LeadDocument $document) => filled(data_get($document->metadata, 'requirement_key')))
            ->sortByDesc('id')
            ->unique(fn (LeadDocument $document) => data_get($document->metadata, 'requirement_key'))
            ->keyBy(fn (LeadDocument $document) => data_get($document->metadata, 'requirement_key'));
    }

    private function publicRequirementPayload(Candidate $candidate, array $requirement, ?LeadDocument $document): array
    {
        $status = $document?->status;

        return [
            ...$requirement,
            'uploaded' => (bool) $document,
            'complete' => (bool) $document && $status !== 'REJECTED',
            'status' => $status,
            'review_note' => $document?->review_note,
            'file' => $document ? [
                'name' => $document->original_name,
                'size' => $document->size,
                'mime_type' => $document->mime_type,
                'can_replace' => $status !== 'VERIFIED',
                'preview_url' => URL::temporarySignedRoute('contracting.documents.preview', now()->addMinutes(10), ['candidate' => $candidate, 'token' => 'document-preview', 'document' => $document]),
                'download_url' => URL::temporarySignedRoute('contracting.documents.download', now()->addMinutes(10), ['candidate' => $candidate, 'token' => 'document-download', 'document' => $document]),
            ] : null,
        ];
    }

    private function ensurePublicDocumentBelongsToCandidate(Candidate $candidate, LeadDocument $document): void
    {
        abort_unless($document->lead_id === $candidate->lead_id && $document->type === 'contract_document', 404);
    }

    private function validateContractInvitation(ContractInvitation $invitation, string $token): void
    {
        abort_unless($invitation->status === 'INVITED', 410, 'Esta invitación ya no está disponible.');
        abort_unless($invitation->invitation_expires_at?->isFuture(), 410, 'Esta invitación ya venció.');
        abort_unless(hash_equals($invitation->token_hash, hash('sha256', $token)), 403, 'El enlace de contratación no es válido.');
        abort_unless($invitation->candidate?->status === CandidateStatus::WAITING, 422, 'La candidata ya no está disponible para agendar contratación.');
    }
}
