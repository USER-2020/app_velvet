<?php

namespace App\Http\Controllers;

use App\Enums\CandidateType;
use App\Models\Candidate;
use App\Models\OperationalRequest;
use App\Models\OperationalShift;
use App\Models\PlatformCatalog;
use App\Models\RoomReservation;
use App\Models\Agent\AgentWorkSession;
use App\Services\RealtimePublisher;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ModelPortalController extends Controller
{
    public function time(Request $request): Response
    {
        $sessionsQuery = AgentWorkSession::query()->where('user_id', $request->user()->id);
        $summaryQuery = clone $sessionsQuery;
        $summary = [
            'sessions' => (clone $summaryQuery)->count(),
            'active' => (clone $summaryQuery)->where('status', 'active')->count(),
            'connectedSeconds' => (int) (clone $summaryQuery)->sum('total_connected_seconds'),
            'streamingSeconds' => (int) (clone $summaryQuery)->sum('total_streaming_seconds'),
        ];

        $sessions = $sessionsQuery->with('station:id,hostname,studio_id')
            ->latest('started_at')
            ->limit(100)
            ->get()
            ->map(fn (AgentWorkSession $session) => [
                'id' => $session->id,
                'startedAt' => $session->started_at?->timezone('America/Bogota')->toIso8601String(),
                'endedAt' => $session->ended_at?->timezone('America/Bogota')->toIso8601String(),
                'status' => $session->status,
                'endReason' => $session->end_reason,
                'totalConnectedSeconds' => (int) $session->total_connected_seconds,
                'totalStreamingSeconds' => (int) $session->total_streaming_seconds,
                'station' => $session->station ? [
                    'hostname' => $session->station->hostname,
                    'studioId' => $session->station->studio_id,
                ] : null,
                'obs' => $session->last_obs,
            ])->values();

        return Inertia::render('Portal/Model/Time', [
            'summary' => $summary,
            'sessions' => $sessions,
            'timeoutSeconds' => config('agent.session_timeout_seconds'),
        ]);
    }

    public function activity(Request $request): Response
    {
        $candidate = $this->modelCandidate($request);
        $activities = $candidate->activities()
            ->with('user:id,name')
            ->latest('created_at')
            ->limit(100)
            ->get()
            ->map(fn ($activity) => [
                'id' => 'activity-'.$activity->id,
                'kind' => 'activity',
                'type' => $activity->type,
                'description' => $activity->description,
                'createdAt' => $activity->created_at?->timezone('America/Bogota')->toIso8601String(),
                'actor' => $activity->user?->name ?: 'Sistema',
            ]);

        $requests = OperationalRequest::query()
            ->with('resolvedBy:id,name')
            ->where(fn ($query) => $query->where('candidate_id', $candidate->id)->orWhere('requested_by', $request->user()->id))
            ->latest('created_at')
            ->limit(100)
            ->get()
            ->map(fn (OperationalRequest $item) => [
                'id' => 'request-'.$item->id,
                'kind' => 'request',
                'type' => $item->type,
                'description' => $item->details,
                'response' => $item->response,
                'status' => $item->status,
                'createdAt' => $item->created_at?->timezone('America/Bogota')->toIso8601String(),
                'actor' => $item->resolvedBy?->name ?: 'Solicitud propia',
            ]);

        return Inertia::render('Portal/Model/Activity', [
            'candidate' => ['name' => $candidate->lead?->full_name, 'code' => $candidate->code, 'status' => $candidate->status?->value ?? $candidate->status],
            'events' => $activities->concat($requests)->sortByDesc('createdAt')->values(),
        ]);
    }

    public function agenda(Request $request): Response
    {
        $candidate = $this->modelCandidate($request);
        $appointments = $candidate->contractAppointments()
            ->with('slot')
            ->whereIn('status', ['SCHEDULED', 'COMPLETED'])
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($appointment) => [
                'id' => 'appointment-'.$appointment->id,
                'kind' => 'appointment',
                'title' => 'Cita de contratación',
                'status' => $appointment->status,
                'startsAt' => $appointment->slot?->starts_at?->timezone('America/Bogota')->toIso8601String(),
                'endsAt' => $appointment->slot?->ends_at?->timezone('America/Bogota')->toIso8601String(),
                'notes' => $appointment->notes,
            ]);

        $shifts = OperationalShift::query()
            ->where('model_candidate_id', $candidate->id)
            ->with('roomReservation.room:id,name,code')
            ->whereIn('status', ['SCHEDULED', 'IN_PROGRESS', 'COMPLETED'])
            ->where('ends_at', '>=', now('UTC')->subDays(30))
            ->latest('starts_at')
            ->limit(50)
            ->get()
            ->map(fn (OperationalShift $shift) => [
                'id' => 'shift-'.$shift->id,
                'kind' => 'shift',
                'title' => 'Turno operativo',
                'status' => $shift->status,
                'startsAt' => $shift->starts_at?->timezone('America/Bogota')->toIso8601String(),
                'endsAt' => $shift->ends_at?->timezone('America/Bogota')->toIso8601String(),
                'notes' => $shift->notes,
                'room' => $shift->roomReservation?->room?->name,
            ]);

        $reservations = RoomReservation::query()
            ->where(fn ($query) => $query->where('candidate_id', $candidate->id)->orWhere('user_id', $request->user()->id))
            ->with('room:id,name,code')
            ->where('ends_at', '>=', now('UTC')->subDays(30))
            ->latest('starts_at')
            ->limit(50)
            ->get()
            ->map(fn (RoomReservation $reservation) => [
                'id' => 'reservation-'.$reservation->id,
                'kind' => 'room',
                'title' => $reservation->purpose ?: 'Reserva de room',
                'status' => $reservation->status,
                'startsAt' => $reservation->starts_at?->timezone('America/Bogota')->toIso8601String(),
                'endsAt' => $reservation->ends_at?->timezone('America/Bogota')->toIso8601String(),
                'notes' => $reservation->notes,
                'room' => $reservation->room?->name,
            ]);

        return Inertia::render('Portal/Model/Agenda', [
            'items' => $appointments->concat($shifts)->concat($reservations)->sortBy('startsAt')->values(),
        ]);
    }

    public function requests(Request $request): Response
    {
        $candidate = $this->modelCandidate($request);
        $items = OperationalRequest::query()
            ->where(fn ($query) => $query->where('candidate_id', $candidate->id)->orWhere('requested_by', $request->user()->id))
            ->with('resolvedBy:id,name')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (OperationalRequest $item) => [
                'id' => $item->id,
                'type' => $item->type,
                'status' => $item->status,
                'details' => $item->details,
                'response' => $item->response,
                'createdAt' => $item->created_at?->timezone('America/Bogota')->toIso8601String(),
                'resolvedAt' => $item->resolved_at?->timezone('America/Bogota')->toIso8601String(),
                'resolvedBy' => $item->resolvedBy?->name,
            ]);

        return Inertia::render('Portal/Model/Requests', [
            'requests' => $items,
            'canCreate' => $request->user()->hasPermissionTo('requests.create'),
        ]);
    }

    public function platformRequests(Request $request): Response
    {
        $candidate = $this->modelCandidate($request);
        $items = OperationalRequest::query()
            ->where('candidate_id', $candidate->id)
            ->where('type', 'PLATFORM_REQUEST')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (OperationalRequest $item) => [
                'id' => $item->id,
                'platform' => data_get($item->metadata, 'platform_name', 'Plataforma'),
                'nickname' => data_get($item->metadata, 'nickname', '—'),
                'status' => $item->status,
                'details' => $item->details,
                'response' => $item->response,
                'createdAt' => $item->created_at?->timezone('America/Bogota')->toIso8601String(),
            ]);

        return Inertia::render('Portal/Model/PlatformRequests', [
            'platforms' => PlatformCatalog::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'requests' => $items,
            'canCreate' => $request->user()->hasPermissionTo('requests.create'),
        ]);
    }

    public function storePlatformRequest(Request $request): \Illuminate\Http\RedirectResponse
    {
        abort_unless($request->user()->hasPermissionTo('requests.create'), 403);
        $candidate = $this->modelCandidate($request);
        $data = $request->validate([
            'platform_catalog_id' => ['required', 'integer', 'exists:platform_catalogs,id'],
            'nickname' => ['required', 'string', 'min:3', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/'],
        ]);
        $platform = PlatformCatalog::query()->whereKey($data['platform_catalog_id'])->where('is_active', true)->firstOrFail();
        $pending = OperationalRequest::query()
            ->where('candidate_id', $candidate->id)
            ->where('type', 'PLATFORM_REQUEST')
            ->where('status', 'PENDING')
            ->where('metadata->platform_catalog_id', $platform->id)
            ->exists();

        if ($pending) {
            return back()->withErrors(['platform_catalog_id' => 'Ya tienes una solicitud pendiente para esta plataforma.']);
        }

        $operationalRequest = OperationalRequest::create([
            'candidate_id' => $candidate->id,
            'requested_by' => $request->user()->id,
            'type' => 'PLATFORM_REQUEST',
            'status' => 'PENDING',
            'details' => "Solicitud de nickname {$data['nickname']} para {$platform->name}.",
            'metadata' => ['platform_catalog_id' => $platform->id, 'platform_code' => $platform->code, 'platform_name' => $platform->name, 'nickname' => $data['nickname']],
        ]);

        app(RealtimePublisher::class)->publish('operation.request.created', [
            'request_id' => $operationalRequest->id,
            'candidate_id' => $candidate->id,
            'requested_by' => $request->user()->id,
            'type' => $operationalRequest->type,
            'status' => $operationalRequest->status,
            'platform_catalog_id' => $platform->id,
            'platform_name' => $platform->name,
            'nickname' => $data['nickname'],
            'updated_at' => $operationalRequest->updated_at?->toIso8601String(),
        ]);

        return back()->with('success', 'Solicitud de plataforma enviada para validación.');
    }

    private function modelCandidate(Request $request): Candidate
    {
        $candidate = Candidate::query()
            ->with('lead:id,first_name,last_name')
            ->where('user_id', $request->user()->id)
            ->where('candidate_type', CandidateType::MODEL->value)
            ->first();

        abort_unless($candidate, 404, 'No hay un perfil de modelo vinculado a esta cuenta.');

        return $candidate;
    }
}
