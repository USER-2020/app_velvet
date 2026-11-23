<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\Room;
use App\Models\RoomReservation;
use App\Models\RoomUsageLog;
use App\Models\User;
use App\Services\RealtimePublisher;
use App\Services\RoomReservationNotificationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RoomController extends Controller
{
    public function index(Request $request): Response
    {
        $now = now('America/Bogota');
        $rooms = Room::query()
            ->with(['reservations' => function ($query) use ($now) {
                $query->whereIn('status', ['SCHEDULED', 'IN_USE'])
                    ->where('ends_at', '>=', $now->copy()->utc())
                    ->orderBy('starts_at')
                    ->with(['candidate.lead:id,first_name,last_name', 'user:id,name', 'reservedBy:id,name']);
            }])
            ->orderBy('code')
            ->get()
            ->map(fn (Room $room) => $this->roomPayload($room, $now))
            ->values();

        $candidates = Candidate::query()
            ->with('lead:id,first_name,last_name')
            ->where('candidate_type', 'MODEL')
            ->whereNotIn('status', ['DISCARDED', 'WITHDRAWN'])
            ->orderBy('id', 'desc')
            ->get(['id', 'code', 'lead_id', 'candidate_type', 'status'])
            ->map(fn (Candidate $candidate) => [
                'id' => $candidate->id,
                'code' => $candidate->code,
                'name' => $candidate->lead?->full_name,
                'status' => $candidate->status?->value,
            ])
            ->values();

        $canManage = $this->canManage($request);

        return Inertia::render('Admin/Rooms/Index', [
            'rooms' => $rooms,
            'candidates' => $candidates,
            'users' => $canManage ? User::query()
                ->whereHas('roles')
                ->whereDoesntHave('roles', fn ($query) => $query->whereIn('slug', ['super_admin', 'admin', 'monitor', 'model']))
                ->orderBy('name')
                ->get(['id', 'name']) : [],
            'usageLogs' => RoomUsageLog::query()
                ->with(['room:id,code,name', 'changedBy:id,name', 'reservation:id,purpose,candidate_id,user_id'])
                ->latest('created_at')
                ->limit(30)
                ->get()
                ->map(fn (RoomUsageLog $log) => [
                    'id' => $log->id,
                    'room' => $log->room?->name,
                    'from_status' => $log->from_status,
                    'to_status' => $log->to_status,
                    'notes' => $log->notes,
                    'changed_by' => $log->changedBy?->name,
                    'created_at' => $log->created_at?->timezone('America/Bogota')->toIso8601String(),
                ])
                ->values(),
            'canManage' => $canManage,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request);
        $data = $request->validate($this->roomRules());
        $room = Room::create(array_merge($data, ['created_by' => $request->user()->id]));

        $this->publish('room.created', ['room_id' => $room->id]);

        return back()->with('success', 'Room creada correctamente.');
    }

    public function update(Request $request, Room $room): RedirectResponse
    {
        $this->authorizeManagement($request);
        $data = $request->validate($this->roomRules($room));
        $room->update($data);

        $this->publish('room.updated', ['room_id' => $room->id]);

        return back()->with('success', 'Room actualizada correctamente.');
    }

    public function storeReservation(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request);
        $data = $request->validate([
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'candidate_id' => ['nullable', 'integer', 'exists:candidates,id', 'required_without:user_id', 'prohibited_with:user_id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id', 'required_without:candidate_id', 'prohibited_with:candidate_id'],
            'purpose' => ['required', 'string', 'max:180'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $startsAt = Carbon::parse($data['starts_at'], 'America/Bogota')->utc();
        $endsAt = Carbon::parse($data['ends_at'], 'America/Bogota')->utc();
        if ($startsAt->isPast()) {
            throw ValidationException::withMessages(['starts_at' => 'La reserva debe comenzar en el futuro.']);
        }

        $candidate = ! empty($data['candidate_id']) ? Candidate::findOrFail($data['candidate_id']) : null;
        if ($candidate && $candidate->candidate_type?->value !== 'MODEL') {
            throw ValidationException::withMessages(['candidate_id' => 'Solo se pueden asignar modelos a una room.']);
        }
        if (! empty($data['user_id']) && User::query()
            ->whereKey($data['user_id'])
            ->whereHas('roles', fn ($query) => $query->whereIn('slug', ['super_admin', 'admin', 'monitor', 'model']))
            ->exists()) {
            throw ValidationException::withMessages(['user_id' => 'Selecciona un usuario operativo, no un usuario de administración.']);
        }

        try {
            $reservation = DB::transaction(function () use ($request, $data, $startsAt, $endsAt, $candidate) {
                $room = Room::query()->lockForUpdate()->findOrFail($data['room_id']);
                if (! $room->is_active || $room->status !== 'AVAILABLE') {
                    throw ValidationException::withMessages(['room_id' => 'La room no está disponible para nuevas reservas.']);
                }

                $overlap = RoomReservation::query()
                    ->where('room_id', $room->id)
                    ->whereIn('status', ['SCHEDULED', 'IN_USE'])
                    ->where('starts_at', '<', $endsAt)
                    ->where('ends_at', '>', $startsAt)
                    ->lockForUpdate()
                    ->exists();
                if ($overlap) {
                    throw ValidationException::withMessages(['starts_at' => 'La room ya está reservada en ese horario.']);
                }

                if ($candidate && RoomReservation::query()
                    ->where('candidate_id', $candidate->id)
                    ->whereIn('status', ['SCHEDULED', 'IN_USE'])
                    ->where('starts_at', '<', $endsAt)
                    ->where('ends_at', '>', $startsAt)
                    ->exists()) {
                    throw ValidationException::withMessages(['candidate_id' => 'La modelo ya tiene otra room asignada en ese horario.']);
                }

                if (! empty($data['user_id']) && RoomReservation::query()
                    ->where('user_id', $data['user_id'])
                    ->whereIn('status', ['SCHEDULED', 'IN_USE'])
                    ->where('starts_at', '<', $endsAt)
                    ->where('ends_at', '>', $startsAt)
                    ->exists()) {
                    throw ValidationException::withMessages(['user_id' => 'El usuario ya tiene otra room asignada en ese horario.']);
                }

                return RoomReservation::create([
                    'room_id' => $room->id,
                    'candidate_id' => $data['candidate_id'] ?? null,
                    'user_id' => $data['user_id'] ?? null,
                    'reserved_by' => $request->user()->id,
                    'purpose' => $data['purpose'],
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'status' => 'SCHEDULED',
                    'notes' => $data['notes'] ?? null,
                ]);
            });
        } catch (QueryException $exception) {
            report($exception);
            throw ValidationException::withMessages(['room_id' => 'No se pudo reservar la room. Inténtalo nuevamente.']);
        }

        app(RoomReservationNotificationService::class)->notify($request->user(), $reservation);

        return back()->with('success', 'Room reservada correctamente.');
    }

    public function updateReservationStatus(Request $request, RoomReservation $reservation): RedirectResponse
    {
        $this->authorizeManagement($request);
        $data = $request->validate(['status' => ['required', Rule::in(['IN_USE', 'COMPLETED', 'CANCELLED', 'NO_SHOW'])]]);

        DB::transaction(function () use ($request, $reservation, $data) {
            $reservation->refresh();
            $previous = $reservation->status;
            $next = $data['status'];
            $allowed = match ($next) {
                'IN_USE' => ['SCHEDULED'],
                'COMPLETED' => ['SCHEDULED', 'IN_USE'],
                'CANCELLED', 'NO_SHOW' => ['SCHEDULED'],
            };
            if (! in_array($previous, $allowed, true)) {
                throw ValidationException::withMessages(['status' => "No se puede pasar de {$previous} a {$next}."]);
            }

            $now = now('America/Bogota')->utc();
            $attributes = ['status' => $next];
            if ($next === 'IN_USE') {
                $attributes['checked_in_at'] = $reservation->checked_in_at ?: $now;
                $attributes['started_at'] = $reservation->started_at ?: $now;
            }
            if (in_array($next, ['COMPLETED', 'CANCELLED', 'NO_SHOW'], true)) $attributes['ended_at'] = $now;
            $reservation->update($attributes);

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

        return back()->with('success', 'Estado de la reserva actualizado.');
    }

    public function updateStatus(Request $request, Room $room): RedirectResponse
    {
        $this->authorizeManagement($request);
        $data = $request->validate(['status' => ['required', Rule::in(['AVAILABLE', 'MAINTENANCE', 'BLOCKED'])]]);
        $previous = $room->status;
        $room->update(['status' => $data['status']]);
        RoomUsageLog::create([
            'room_id' => $room->id,
            'changed_by' => $request->user()->id,
            'from_status' => $previous,
            'to_status' => $data['status'],
            'notes' => 'Cambio manual de disponibilidad.',
        ]);
        $this->publish('room.status.changed', ['room_id' => $room->id]);

        return back()->with('success', 'Disponibilidad de la room actualizada.');
    }

    private function roomRules(?Room $room = null): array
    {
        return [
            'code' => ['required', 'string', 'max:40', Rule::unique('rooms', 'code')->ignore($room?->id)],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['AVAILABLE', 'MAINTENANCE', 'BLOCKED'])],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function roomPayload(Room $room, Carbon $now): array
    {
        $reservations = $room->reservations;
        $current = $reservations->first(fn (RoomReservation $item) => $item->starts_at <= $now && $item->ends_at > $now);
        $upcoming = $reservations->first(fn (RoomReservation $item) => $item->starts_at > $now);
        $effectiveStatus = $room->status;
        if ($room->status === 'AVAILABLE') $effectiveStatus = $current ? ($current->status === 'IN_USE' ? 'IN_USE' : 'RESERVED') : ($upcoming ? 'RESERVED' : 'AVAILABLE');

        return [
            'id' => $room->id,
            'code' => $room->code,
            'name' => $room->name,
            'description' => $room->description,
            'status' => $room->status,
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
            'purpose' => $reservation->purpose,
            'status' => $reservation->status,
            'starts_at' => $reservation->starts_at?->timezone('America/Bogota')->toIso8601String(),
            'ends_at' => $reservation->ends_at?->timezone('America/Bogota')->toIso8601String(),
            'candidate_id' => $reservation->candidate_id,
            'candidate_name' => $reservation->candidate?->lead?->full_name,
            'candidate_code' => $reservation->candidate?->code,
            'user_id' => $reservation->user_id,
            'user_name' => $reservation->user?->name,
            'reserved_by' => $reservation->reservedBy?->name,
            'notes' => $reservation->notes,
        ];
    }

    private function canManage(Request $request): bool
    {
        return $request->user()->hasPermissionTo('rooms.manage') || $request->user()->hasPermissionTo('rooms.manage_team');
    }

    private function authorizeManagement(Request $request): void
    {
        abort_unless($this->canManage($request), 403);
    }

    private function publish(string $event, array $data): void
    {
        app(RealtimePublisher::class)->publish($event, $data);
    }
}
