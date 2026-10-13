<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\Room;
use App\Models\RoomReservation;
use App\Models\RoomUsageLog;
use App\Enums\CandidateStatus;
use App\Models\LeadDocument;
use App\Models\TrainingRecord;
use App\Services\RealtimePublisher;
use App\Services\AuditLogger;
use App\Services\OperationalSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PortalRoomController extends Controller
{
    /** Shared, authorization must be enforced by the caller before using these payloads. */
    public function modelProfileForMonitor(Candidate $candidate): array
    {
        return $this->modelProfilePayload($candidate);
    }

    public function statisticsForMonitor(Candidate $candidate): array
    {
        return $this->statisticsForModels([$candidate->id]);
    }

    public function platformAccountsForMonitor(Candidate $candidate): array
    {
        return $this->platformAccountsFor($candidate);
    }

    public function modelDashboard(Request $request): Response
    {
        if ($this->isTraining($request)) {
            return Inertia::render('Portal/Model/Dashboard', $this->trainingModelPayload($request));
        }

        $candidate = $this->modelCandidate($request);
        return Inertia::render('Portal/Model/Dashboard', [
            'candidate' => $candidate ? $this->candidatePayload($candidate) : null,
            'statistics' => $candidate ? $this->statisticsForModels([$candidate->id]) : $this->emptyStatistics(),
            'activityTrend' => $candidate ? $this->activityTrendForModels([$candidate->id]) : $this->emptyActivityTrend(),
        ]);
    }

    public function modelRooms(Request $request): Response
    {
        if ($this->isTraining($request)) {
            $payload = $this->trainingModelPayload($request);
            return Inertia::render('Portal/Model/Rooms', $payload);
        }

        $candidate = $this->modelCandidate($request);
        $operationalActive = $candidate?->status === CandidateStatus::ACTIVE && (bool) $candidate?->user?->is_active;

        return Inertia::render('Portal/Model/Rooms', [
            'candidate' => $candidate ? $this->candidatePayload($candidate) : null,
            'rooms' => $this->roomsPayload(),
            'reservations' => $candidate ? $this->reservationQuery()->where('candidate_id', $candidate->id)->latest('starts_at')->limit(30)->get()->map(fn (RoomReservation $reservation) => $this->reservationPayload($reservation))->values() : [],
            'canReserve' => $operationalActive && $request->user()->hasPermissionTo('rooms.reserve_own'),
            'canUse' => $operationalActive && $request->user()->hasPermissionTo('rooms.use_own'),
        ]);
    }

    public function modelProfile(Request $request): Response
    {
        if ($this->isTraining($request)) {
            return Inertia::render('Portal/Model/Profile', [
                'profile' => $this->trainingProfilePayload($request),
                'statistics' => $this->emptyStatistics(),
                'documents' => $this->ownDocuments($request),
                'platformAccounts' => [],
                'trainingMode' => true,
            ]);
        }

        $candidate = $this->modelCandidate($request);
        abort_unless($candidate, 403, 'Tu usuario todavía no tiene una modelo vinculada.');

        return Inertia::render('Portal/Model/Profile', [
            'profile' => $this->modelProfilePayload($candidate),
            'statistics' => $this->statisticsForModels([$candidate->id]),
            'documents' => $this->ownDocuments($request),
            'platformAccounts' => $this->platformAccountsFor($candidate),
        ]);
    }

    public function monitorDashboard(Request $request): Response
    {
        if ($this->isTraining($request)) {
            return Inertia::render('Portal/Monitor/Overview', $this->trainingMonitorPayload($request));
        }

        return Inertia::render('Portal/Monitor/Overview', $this->monitorPayload($request));
    }

    public function monitorRooms(Request $request): Response
    {
        if ($this->isTraining($request)) {
            return Inertia::render('Portal/Monitor/Dashboard', $this->trainingMonitorPayload($request));
        }

        return Inertia::render('Portal/Monitor/Dashboard', $this->monitorPayload($request));
    }

    private function monitorPayload(Request $request): array
    {
        $operationalActive = $request->user()->candidate?->status === CandidateStatus::ACTIVE && (bool) $request->user()->is_active;
        $monitorCandidateId = Candidate::query()->where('user_id', $request->user()->id)->value('id');
        $reservations = $this->reservationQuery()
            ->whereIn('status', ['SCHEDULED', 'IN_USE'])
            ->where('ends_at', '>=', now('America/Bogota')->copy()->subHours(2)->utc())
            ->orderBy('starts_at')
            ->limit(100)
            ->get()
            ->map(fn (RoomReservation $reservation) => $this->reservationPayload($reservation))
            ->values();

        $assignedModels = Candidate::query()
            ->where('candidate_type', 'MODEL')
            ->when($monitorCandidateId, fn ($query) => $query->whereHas('modelAssignments', fn ($assignments) => $assignments
                ->where('monitor_candidate_id', $monitorCandidateId)
                ->whereNull('ended_at')), fn ($query) => $query->whereRaw('1 = 0'));
        $assignedModelIds = $assignedModels->pluck('id')->all();

        return [
            'rooms' => $this->roomsPayload(),
            'reservations' => $reservations,
            'candidates' => $assignedModels
                ->with('lead:id,first_name,last_name')
                ->whereNotIn('status', ['DISCARDED', 'WITHDRAWN'])
                ->orderBy('id', 'desc')
                ->get(['id', 'code', 'lead_id'])
                ->map(fn (Candidate $candidate) => $this->candidatePayload($candidate))
                ->values(),
            'statistics' => [...$this->statisticsForModels($assignedModelIds), 'assigned_models' => count($assignedModelIds)],
            'activityTrend' => $this->activityTrendForModels($assignedModelIds),
            'canReserve' => $operationalActive && $request->user()->hasPermissionTo('rooms.manage_reservations'),
            'canUse' => $operationalActive && $request->user()->hasPermissionTo('rooms.manage_usage'),
            'operationalActive' => $operationalActive,
            'contractDocuments' => $this->ownDocuments($request),
        ];
    }

    public function previewContractDocument(Request $request, LeadDocument $document)
    {
        $this->authorizeContractDocument($request, $document);
        app(AuditLogger::class)->record('portal_documents.previewed', $document, ['lead_id' => $document->lead_id, 'type' => $document->type]);
        return response()->file(Storage::disk('local')->path($document->path), ['Content-Type' => $document->mime_type, 'Content-Disposition' => 'inline; filename="' . addslashes($document->original_name) . '"']);
    }

    public function downloadContractDocument(Request $request, LeadDocument $document)
    {
        $this->authorizeContractDocument($request, $document);
        app(AuditLogger::class)->record('portal_documents.downloaded', $document, ['lead_id' => $document->lead_id, 'type' => $document->type]);
        return Storage::disk('local')->download($document->path, $document->original_name, ['Content-Type' => $document->mime_type]);
    }

    public function storeModelReservation(Request $request): RedirectResponse
    {
        if ($this->isTraining($request)) {
            return $this->storeTrainingReservation($request, false);
        }

        abort_unless($request->user()->hasPermissionTo('rooms.reserve_own'), 403);
        $candidate = $this->modelCandidate($request);
        abort_unless($candidate, 403, 'Tu usuario todavía no tiene una modelo vinculada.');

        $data = $request->validate($this->reservationRules());
        $reservation = $this->createReservation($request, $data, $candidate, null);

        $this->publish('room.reservation.created', ['room_id' => $reservation->room_id, 'reservation_id' => $reservation->id]);

        return back()->with('success', 'La room quedó reservada correctamente.');
    }

    public function storeMonitorReservation(Request $request): RedirectResponse
    {
        if ($this->isTraining($request)) {
            return $this->storeTrainingReservation($request, true);
        }

        abort_unless($request->user()->hasPermissionTo('rooms.manage_reservations'), 403);
        $data = $request->validate(array_merge($this->reservationRules(), [
            'candidate_id' => ['required', 'integer', 'exists:candidates,id'],
        ]));
        $monitorCandidateId = Candidate::query()->where('user_id', $request->user()->id)->value('id');
        $candidate = Candidate::query()
            ->where('candidate_type', 'MODEL')
            ->whereKey($data['candidate_id'])
            ->whereHas('modelAssignments', fn ($assignments) => $assignments
                ->where('monitor_candidate_id', $monitorCandidateId)
                ->whereNull('ended_at'))
            ->firstOrFail();
        $reservation = $this->createReservation($request, $data, $candidate, null);

        $this->publish('room.reservation.created', ['room_id' => $reservation->room_id, 'reservation_id' => $reservation->id]);

        return back()->with('success', 'La reserva de la modelo quedó programada.');
    }

    public function updateModelReservationStatus(Request $request, int $reservation): RedirectResponse
    {
        if ($this->isTraining($request)) {
            return $this->updateTrainingReservationStatus($request, false);
        }

        $reservation = RoomReservation::findOrFail($reservation);
        abort_unless($request->user()->hasPermissionTo('rooms.use_own'), 403);
        $candidate = $this->modelCandidate($request);
        abort_unless($candidate && $reservation->candidate_id === $candidate->id, 403);
        $this->changeReservationStatus($request, $reservation, ['IN_USE', 'COMPLETED']);

        return back()->with('success', 'El estado de tu uso fue actualizado.');
    }

    public function updateMonitorReservationStatus(Request $request, int $reservation): RedirectResponse
    {
        if ($this->isTraining($request)) {
            return $this->updateTrainingReservationStatus($request, true);
        }

        $reservation = RoomReservation::findOrFail($reservation);
        abort_unless($request->user()->hasPermissionTo('rooms.manage_usage'), 403);
        if ($request->user()->hasRole('monitor')) {
            $monitorCandidateId = Candidate::query()->where('user_id', $request->user()->id)->value('id');
            abort_unless($monitorCandidateId && $reservation->candidate_id && Candidate::query()
                ->whereKey($reservation->candidate_id)
                ->whereHas('modelAssignments', fn ($assignment) => $assignment
                    ->where('monitor_candidate_id', $monitorCandidateId)
                    ->whereNull('ended_at'))
                ->exists(), 403);
        }
        $data = $request->validate(['status' => ['required', Rule::in(['IN_USE', 'COMPLETED', 'CANCELLED', 'NO_SHOW'])]]);
        $this->changeReservationStatus($request, $reservation, [$data['status']]);

        return back()->with('success', 'El estado de la reserva fue actualizado en tiempo real.');
    }

    public function realtimeToken(Request $request): JsonResponse
    {
        abort_unless(collect(['rooms.view', 'rooms.view_own', 'rooms.view_team'])->contains(fn (string $permission) => $request->user()->hasPermissionTo($permission)), 403);
        abort_unless(config('services.ably.api_key'), 503, 'Ably no está configurado.');
        [$keyName, $keySecret] = explode(':', config('services.ably.api_key'), 2);
        $response = Http::withBasicAuth($keyName, $keySecret)->acceptJson()->post("https://rest.ably.io/keys/{$keyName}/requestToken", [
            'keyName' => $keyName,
            'capability' => json_encode([config('services.ably.channels.rooms') => ['subscribe']]),
            'ttl' => 3600000,
            'timestamp' => now()->valueOf(),
        ]);

        abort_unless($response->successful(), 502, 'No se pudo conectar al servicio realtime.');

        return response()->json($response->json());
    }

    private function isTraining(Request $request): bool
    {
        return $request->user()?->hasRole(['model', 'monitor'])
            && ($request->user()->candidate?->status !== CandidateStatus::ACTIVE || ! $request->user()->is_active);
    }

    private function trainingModelPayload(Request $request): array
    {
        $records = $this->trainingRecordsFor($request);

        return [
            'candidate' => ['name' => $request->user()->name],
            'rooms' => $this->trainingRoomsPayload($records),
            'reservations' => $this->trainingReservationsPayload($records, $request->user()->id),
            'canReserve' => $request->user()->hasPermissionTo('rooms.reserve_own'),
            'canUse' => $request->user()->hasPermissionTo('rooms.use_own'),
            'operationalActive' => false,
            'trainingMode' => true,
            'contractDocuments' => $this->ownDocuments($request),
            'statistics' => $this->emptyStatistics(),
            'activityTrend' => $this->emptyActivityTrend(),
        ];
    }

    private function trainingMonitorPayload(Request $request): array
    {
        $records = $this->trainingRecordsFor($request);

        return [
            'rooms' => $this->trainingRoomsPayload($records),
            'reservations' => $this->trainingReservationsPayload($records),
            'candidates' => $records->where('type', 'model')->map(fn (TrainingRecord $record) => [
                'id' => $record->id,
                'code' => $record->payload['code'] ?? $record->record_key,
                'name' => $record->payload['name'] ?? 'Modelo de prueba',
            ])->values(),
            'canReserve' => $request->user()->hasPermissionTo('rooms.manage_reservations'),
            'canUse' => $request->user()->hasPermissionTo('rooms.manage_usage'),
            'operationalActive' => false,
            'trainingMode' => true,
            'contractDocuments' => [],
            'statistics' => $this->emptyStatistics(),
            'activityTrend' => $this->emptyActivityTrend(),
        ];
    }

    private function trainingRecordsFor(Request $request)
    {
        return TrainingRecord::query()
            ->where(function ($query) use ($request) {
                $query->whereNull('owner_user_id')->orWhere('owner_user_id', $request->user()->id);
            })
            ->get();
    }

    private function trainingRoomsPayload($records): array
    {
        $now = now('America/Bogota');
        $reservations = $this->trainingReservationsPayload($records);

        return $records->where('type', 'room')->map(function (TrainingRecord $record) use ($reservations, $now) {
            $payload = $record->payload;
            $roomReservations = collect($reservations)->where('room_id', $record->id)->whereIn('status', ['SCHEDULED', 'IN_USE']);
            $current = $roomReservations->first(fn ($item) => Carbon::parse($item['starts_at'])->lte($now) && Carbon::parse($item['ends_at'])->gt($now));
            $upcoming = $roomReservations->first(fn ($item) => Carbon::parse($item['starts_at'])->gt($now));
            $status = $payload['status'] ?? 'AVAILABLE';
            if ($status === 'AVAILABLE') $status = $current ? ($current['status'] === 'IN_USE' ? 'IN_USE' : 'RESERVED') : ($upcoming ? 'RESERVED' : 'AVAILABLE');

            return ['id' => $record->id, 'code' => $payload['code'], 'name' => $payload['name'], 'description' => $payload['description'] ?? null, 'status' => $status, 'effective_status' => $status, 'is_active' => true, 'current' => $current, 'upcoming' => $upcoming];
        })->values()->all();
    }

    private function trainingReservationsPayload($records, ?int $ownerUserId = null): array
    {
        $rooms = $records->where('type', 'room')->keyBy('record_key');
        $models = $records->where('type', 'model')->keyBy('record_key');

        return $records->where('type', 'reservation')->filter(fn (TrainingRecord $record) => $ownerUserId === null || $record->owner_user_id === null || $record->owner_user_id === $ownerUserId)->map(function (TrainingRecord $record) use ($rooms, $models) {
            $payload = $record->payload;
            $room = $rooms->get($payload['room_key']);
            $model = $models->get($payload['model_key']);
            return ['id' => $record->id, 'room_id' => $room?->id, 'room_name' => $room?->payload['name'], 'candidate_id' => $model?->id, 'candidate_name' => $model?->payload['name'], 'purpose' => $payload['purpose'], 'starts_at' => $payload['starts_at'], 'ends_at' => $payload['ends_at'], 'status' => $payload['status'], 'notes' => $payload['notes'] ?? null];
        })->values()->all();
    }

    private function storeTrainingReservation(Request $request, bool $monitor): RedirectResponse
    {
        $rules = $this->reservationRules();
        $rules['room_id'] = ['required', 'integer', 'exists:training_records,id'];
        if ($monitor) $rules['candidate_id'] = ['required', 'integer', 'exists:training_records,id'];
        $data = $request->validate($rules);
        $room = TrainingRecord::query()->where('type', 'room')->findOrFail($data['room_id']);
        $model = $monitor ? TrainingRecord::query()->where('type', 'model')->findOrFail($data['candidate_id']) : TrainingRecord::query()->where('type', 'model')->first();
        $startsAt = Carbon::parse($data['starts_at'], 'America/Bogota');
        $endsAt = Carbon::parse($data['ends_at'], 'America/Bogota');
        abort_if($startsAt->isPast(), 422, 'La reserva debe comenzar en el futuro.');

        TrainingRecord::create(['type' => 'reservation', 'record_key' => 'training-reservation-'.uniqid(), 'owner_user_id' => $monitor ? null : $request->user()->id, 'payload' => ['room_key' => $room->record_key, 'model_key' => $model?->record_key, 'purpose' => $data['purpose'], 'starts_at' => $startsAt->utc()->toIso8601String(), 'ends_at' => $endsAt->utc()->toIso8601String(), 'status' => 'SCHEDULED', 'notes' => $data['notes'] ?? null]]);

        return back()->with('success', 'Registro de práctica creado en el sandbox.');
    }

    private function updateTrainingReservationStatus(Request $request, bool $monitor): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['IN_USE', 'COMPLETED', 'CANCELLED', 'NO_SHOW'])]]);
        $record = TrainingRecord::query()->where('type', 'reservation')->findOrFail($request->route('reservation'));
        abort_unless($monitor || $record->owner_user_id === $request->user()->id || $record->owner_user_id === null, 403);
        $payload = $record->payload;
        $payload['status'] = $data['status'];
        $record->update(['payload' => $payload]);

        return back()->with('success', 'Registro de práctica actualizado.');
    }

    private function modelCandidate(Request $request): ?Candidate
    {
        return Candidate::query()
            ->with('lead:id,first_name,last_name,email')
            ->where('candidate_type', 'MODEL')
            ->where(function ($query) use ($request) {
                $query->where('user_id', $request->user()->id)
                    ->orWhereHas('lead', fn ($lead) => $lead->where('email', $request->user()->email));
            })
            ->first();
    }

    private function ownDocuments(Request $request): array
    {
        $candidate = Candidate::query()->where('user_id', $request->user()->id)->first();
        if (! $candidate) return [];

        return LeadDocument::query()->where('lead_id', $candidate->lead_id)->latest()->get()->map(fn (LeadDocument $document) => [
            'id' => $document->id,
            'name' => $document->original_name,
            'type' => $document->type,
            'status' => $document->status,
            'mime_type' => $document->mime_type,
            'size' => $document->size,
            'created_at' => $document->created_at?->toIso8601String(),
            'preview_url' => route('portal.documents.preview', $document),
            'download_url' => route('portal.documents.download', $document),
        ])->values()->all();
    }

    private function authorizeContractDocument(Request $request, LeadDocument $document): void
    {
        abort_unless(Candidate::query()->where('user_id', $request->user()->id)->where('lead_id', $document->lead_id)->exists(), 403);
        abort_unless(Storage::disk('local')->exists($document->path), 404);
    }

    private function createReservation(Request $request, array $data, Candidate $candidate, ?int $userId): RoomReservation
    {
        $startsAt = Carbon::parse($data['starts_at'], 'America/Bogota')->utc();
        $endsAt = Carbon::parse($data['ends_at'], 'America/Bogota')->utc();
        if ($startsAt->isPast()) {
            throw ValidationException::withMessages(['starts_at' => 'La reserva debe comenzar en el futuro.']);
        }

        return DB::transaction(function () use ($request, $data, $startsAt, $endsAt, $candidate, $userId) {
            $room = Room::query()->lockForUpdate()->findOrFail($data['room_id']);
            if (! $room->is_active || $room->status !== 'AVAILABLE') {
                throw ValidationException::withMessages(['room_id' => 'La room no está disponible para nuevas reservas.']);
            }

            $overlap = fn ($query) => $query->whereIn('status', ['SCHEDULED', 'IN_USE'])
                ->where('starts_at', '<', $endsAt)
                ->where('ends_at', '>', $startsAt)
                ->lockForUpdate();
            if ($overlap(RoomReservation::query()->where('room_id', $room->id))->exists()) {
                throw ValidationException::withMessages(['starts_at' => 'La room ya está reservada en ese horario.']);
            }
            if ($overlap(RoomReservation::query()->where('candidate_id', $candidate->id))->exists()) {
                throw ValidationException::withMessages(['starts_at' => 'La modelo ya tiene otra room asignada en ese horario.']);
            }

            return RoomReservation::create([
                'room_id' => $room->id,
                'candidate_id' => $candidate->id,
                'user_id' => $userId,
                'reserved_by' => $request->user()->id,
                'purpose' => $data['purpose'],
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => 'SCHEDULED',
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }

    private function changeReservationStatus(Request $request, RoomReservation $reservation, array $allowedNext): void
    {
        $sessionService = app(OperationalSessionService::class);
        DB::transaction(function () use ($request, $reservation, $allowedNext, $sessionService) {
            $reservation->refresh();
            $next = request()->validate(['status' => ['sometimes', Rule::in(['IN_USE', 'COMPLETED', 'CANCELLED', 'NO_SHOW'])]])['status'] ?? $allowedNext[0];
            $allowedFrom = match ($next) {
                'IN_USE' => ['SCHEDULED'],
                'COMPLETED' => ['SCHEDULED', 'IN_USE'],
                'CANCELLED', 'NO_SHOW' => ['SCHEDULED'],
            };
            if (! in_array($next, $allowedNext, true) || ! in_array($reservation->status, $allowedFrom, true)) {
                throw ValidationException::withMessages(['status' => "No se puede pasar de {$reservation->status} a {$next}."]);
            }

            $now = now('America/Bogota')->utc();
            $attributes = ['status' => $next];
            if ($next === 'IN_USE') {
                $attributes['checked_in_at'] = $reservation->checked_in_at ?: $now;
                $attributes['started_at'] = $reservation->started_at ?: $now;
            }
            if (in_array($next, ['COMPLETED', 'CANCELLED', 'NO_SHOW'], true)) $attributes['ended_at'] = $now;
            $previous = $reservation->status;
            $reservation->update($attributes);
            if ($next === 'IN_USE') {
                $sessionService->startForReservation($reservation, $request->user()->id, null, $now);
            }
            if ($next === 'COMPLETED') {
                $sessionService->finishForReservation($reservation, $now);
            }
            RoomUsageLog::create([
                'room_id' => $reservation->room_id,
                'room_reservation_id' => $reservation->id,
                'changed_by' => $request->user()->id,
                'from_status' => $previous,
                'to_status' => $next,
                'notes' => $next === 'IN_USE' ? 'Uso iniciado.' : ($next === 'COMPLETED' ? 'Uso finalizado.' : null),
            ]);
        });

        $this->publish('room.reservation.updated', ['room_id' => $reservation->room_id, 'reservation_id' => $reservation->id]);
    }

    private function reservationRules(): array
    {
        return [
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'purpose' => ['required', 'string', 'max:180'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ];
    }

    private function roomsPayload()
    {
        $now = now('America/Bogota');
        return Room::query()->with(['reservations' => function ($query) use ($now) {
            $query->whereIn('status', ['SCHEDULED', 'IN_USE'])
                ->where('ends_at', '>=', $now->copy()->utc())
                ->orderBy('starts_at')
                ->with(['candidate.lead:id,first_name,last_name', 'user:id,name', 'reservedBy:id,name']);
        }])->orderBy('code')->get()->map(fn (Room $room) => $this->roomPayload($room, $now))->values();
    }

    private function reservationQuery()
    {
        return RoomReservation::query()->with(['room:id,code,name', 'candidate.lead:id,first_name,last_name', 'user:id,name', 'reservedBy:id,name']);
    }

    private function roomPayload(Room $room, Carbon $now): array
    {
        $current = $room->reservations->first(fn (RoomReservation $reservation) => $reservation->starts_at <= $now && $reservation->ends_at > $now);
        $upcoming = $room->reservations->first(fn (RoomReservation $reservation) => $reservation->starts_at > $now);
        $effectiveStatus = $room->status;
        if ($room->status === 'AVAILABLE') $effectiveStatus = $current ? ($current->status === 'IN_USE' ? 'IN_USE' : 'RESERVED') : ($upcoming ? 'RESERVED' : 'AVAILABLE');

        return [
            'id' => $room->id,
            'code' => $room->code,
            'name' => $room->name,
            'description' => $room->description,
            'status' => $effectiveStatus,
            'effective_status' => $effectiveStatus,
            'is_active' => $room->is_active,
            'current' => $current ? $this->reservationPayload($current) : null,
            'upcoming' => $upcoming ? $this->reservationPayload($upcoming) : null,
        ];
    }

    private function reservationPayload(RoomReservation $reservation): array
    {
        return [
            'id' => $reservation->id,
            'room_id' => $reservation->room_id,
            'room_name' => $reservation->room?->name,
            'room_code' => $reservation->room?->code,
            'purpose' => $reservation->purpose,
            'status' => $reservation->status,
            'starts_at' => $reservation->starts_at?->timezone('America/Bogota')->toIso8601String(),
            'ends_at' => $reservation->ends_at?->timezone('America/Bogota')->toIso8601String(),
            'checked_in_at' => $reservation->checked_in_at?->timezone('America/Bogota')->toIso8601String(),
            'started_at' => $reservation->started_at?->timezone('America/Bogota')->toIso8601String(),
            'ended_at' => $reservation->ended_at?->timezone('America/Bogota')->toIso8601String(),
            'duration_minutes' => $reservation->started_at && $reservation->ended_at ? max(0, $reservation->started_at->diffInMinutes($reservation->ended_at)) : null,
            'candidate_id' => $reservation->candidate_id,
            'candidate_name' => $reservation->candidate?->lead?->full_name,
            'candidate_code' => $reservation->candidate?->code,
            'reserved_by' => $reservation->reservedBy?->name,
            'notes' => $reservation->notes,
        ];
    }

    private function emptyStatistics(): array
    {
        return [
            'sessions_month' => 0,
            'session_minutes' => 0,
            'tokens_month' => 0,
            'upcoming_reservations' => 0,
            'attended_shifts' => 0,
        ];
    }

    private function emptyActivityTrend(): array
    {
        return collect(range(5, 0))->map(fn ($offset) => ['label' => now('America/Bogota')->subWeeks($offset)->format('d M'), 'sessions' => 0, 'tokens' => 0])->values()->all();
    }

    /** @param array<int> $candidateIds */
    private function activityTrendForModels(array $candidateIds): array
    {
        if ($candidateIds === []) return $this->emptyActivityTrend();

        $from = now('America/Bogota')->subWeeks(5)->startOfWeek()->utc();
        $sessions = DB::table('operational_sessions')->whereIn('candidate_id', $candidateIds)->where('started_at', '>=', $from)->get(['id', 'started_at']);
        $tokensBySession = $sessions->isEmpty() ? collect() : DB::table('production_metrics')->whereIn('operational_session_id', $sessions->pluck('id'))->where('metric_key', 'tokens')->get(['operational_session_id', 'metric_value'])->groupBy('operational_session_id');

        return collect(range(5, 0))->map(function ($offset) use ($sessions, $tokensBySession) {
            $start = now('America/Bogota')->subWeeks($offset)->startOfWeek();
            $end = $start->copy()->endOfWeek();
            $weekSessions = $sessions->filter(fn ($session) => Carbon::parse($session->started_at)->timezone('America/Bogota')->betweenIncluded($start, $end));
            return ['label' => $start->format('d M'), 'sessions' => $weekSessions->count(), 'tokens' => (float) $weekSessions->sum(fn ($session) => $tokensBySession->get($session->id, collect())->sum('metric_value'))];
        })->values()->all();
    }

    /** @param array<int> $candidateIds */
    private function statisticsForModels(array $candidateIds): array
    {
        if ($candidateIds === []) {
            return $this->emptyStatistics();
        }

        $monthStart = now('America/Bogota')->startOfMonth()->utc();
        $sessions = DB::table('operational_sessions')
            ->whereIn('candidate_id', $candidateIds)
            ->where('started_at', '>=', $monthStart)
            ->get(['id', 'started_at', 'ended_at']);
        $sessionIds = $sessions->pluck('id')->all();
        $sessionMinutes = $sessions->sum(fn ($session) => Carbon::parse($session->started_at)->diffInMinutes($session->ended_at ? Carbon::parse($session->ended_at) : now()));

        $tokens = $sessionIds === [] ? 0 : (float) DB::table('production_metrics')
            ->whereIn('operational_session_id', $sessionIds)
            ->where('metric_key', 'tokens')
            ->sum('metric_value');

        $attendedShifts = DB::table('operational_shifts as shift')
            ->join('shift_attendances as attendance', 'attendance.operational_shift_id', '=', 'shift.id')
            ->whereIn('shift.model_candidate_id', $candidateIds)
            ->where('shift.starts_at', '>=', $monthStart)
            ->whereIn('attendance.status', ['PRESENT', 'COMPLETED'])
            ->count();

        return [
            'sessions_month' => $sessions->count(),
            'session_minutes' => $sessionMinutes,
            'tokens_month' => $tokens,
            'upcoming_reservations' => RoomReservation::query()->whereIn('candidate_id', $candidateIds)->whereIn('status', ['SCHEDULED', 'IN_USE'])->where('ends_at', '>=', now())->count(),
            'attended_shifts' => $attendedShifts,
        ];
    }

    private function modelProfilePayload(Candidate $candidate): array
    {
        $candidate->loadMissing(['lead', 'operationalProfile']);
        $assignment = $candidate->currentMonitorAssignment()->with('monitorCandidate.lead')->first();
        $lead = $candidate->lead;
        $profile = $candidate->operationalProfile;

        return [
            'code' => $candidate->code,
            'status' => $candidate->status?->value,
            'personal' => [
                'name' => $lead?->full_name,
                'email' => $lead?->email,
                'phone' => $lead?->phone,
                'city' => $lead?->city,
                'country' => $lead?->country,
                'availability' => $lead?->availability,
                'work_mode' => $lead?->work_mode,
                'experience_years' => $lead?->experience_years,
                'english_level' => $lead?->english_level,
            ],
            'operational' => [
                'status' => $profile?->operational_status,
                'onboarding_completed_at' => $profile?->onboarding_completed_at?->toIso8601String(),
                'induction_completed_at' => $profile?->induction_completed_at?->toIso8601String(),
                'activated_at' => $profile?->activated_at?->toIso8601String(),
                'monitor_name' => $assignment?->monitorCandidate?->lead?->full_name,
            ],
        ];
    }

    private function trainingProfilePayload(Request $request): array
    {
        return [
            'code' => 'SANDBOX',
            'status' => 'INDUCTION',
            'personal' => ['name' => $request->user()->name, 'email' => $request->user()->email],
            'operational' => ['status' => 'TRAINING', 'monitor_name' => null],
        ];
    }

    private function platformAccountsFor(Candidate $candidate): array
    {
        return DB::table('model_platform_accounts as account')
            ->join('studio_accounts as studio', 'studio.id', '=', 'account.studio_account_id')
            ->join('platform_catalogs as platform', 'platform.id', '=', 'studio.platform_catalog_id')
            ->where('account.candidate_id', $candidate->id)
            ->whereNull('account.unlinked_at')
            ->orderBy('platform.name')
            ->get([
                'account.id',
                'platform.name as platform_name',
                'studio.name as studio_name',
                'account.account_identifier',
                'account.ownership_type',
                'account.status',
                'account.is_authorized',
                'account.linked_at',
            ])
            ->map(fn ($account) => [
                'id' => $account->id,
                'platform_name' => $account->platform_name,
                'studio_name' => $account->studio_name,
                'account_identifier' => $account->account_identifier,
                'ownership_type' => $account->ownership_type,
                'status' => $account->status,
                'is_authorized' => (bool) $account->is_authorized,
                'linked_at' => $account->linked_at ? Carbon::parse($account->linked_at)->toIso8601String() : null,
            ])->all();
    }

    private function candidatePayload(Candidate $candidate): array
    {
        return ['id' => $candidate->id, 'code' => $candidate->code, 'name' => $candidate->lead?->full_name];
    }

    private function publish(string $event, array $data): void
    {
        app(RealtimePublisher::class)->publish($event, $data);
    }
}
