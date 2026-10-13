<?php

namespace App\Http\Controllers;

use App\Enums\CandidateStatus;
use App\Enums\CandidateType;
use App\Models\Candidate;
use App\Models\OperationalIncident;
use App\Models\OperationalNote;
use App\Models\OperationalRequest;
use App\Models\OperationalShift;
use App\Models\Room;
use App\Models\RoomOperationChecklist;
use App\Models\RoomReservation;
use App\Models\RoomUsageLog;
use App\Models\ShiftAttendance;
use App\Services\OperationalSessionService;
use App\Services\RealtimePublisher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StudioOperationsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeView($request);
        $from = now('America/Bogota')->startOfDay()->utc();
        $to = now('America/Bogota')->addDays(7)->endOfDay()->utc();
        $shifts = OperationalShift::query()
            ->with(['modelCandidate.lead', 'monitorCandidate.lead', 'roomReservation.room', 'attendance'])
            ->whereBetween('starts_at', [$from, $to])
            ->orderBy('starts_at')
            ->get()
            ->map(fn (OperationalShift $shift) => $this->shiftPayload($shift))
            ->values();

        return Inertia::render('Admin/Operations/Index', [
            'shifts' => $shifts,
            'models' => $this->activeCandidates(CandidateType::MODEL),
            'monitors' => $this->activeCandidates(CandidateType::MONITOR),
            'rooms' => Room::query()->where('is_active', true)->where('status', 'AVAILABLE')->orderBy('code')->get(['id', 'code', 'name']),
            'incidents' => OperationalIncident::query()->with(['candidate.lead', 'room'])->whereIn('status', ['OPEN', 'IN_PROGRESS'])->latest()->limit(20)->get()->map(fn (OperationalIncident $incident) => [
                'id' => $incident->id, 'category' => $incident->category, 'severity' => $incident->severity, 'status' => $incident->status,
                'description' => $incident->description, 'candidate' => $incident->candidate?->lead?->full_name, 'room' => $incident->room?->name,
            ])->values(),
            'requests' => OperationalRequest::query()->with('candidate.lead')->where('status', 'PENDING')->latest()->limit(20)->get()->map(fn (OperationalRequest $item) => [
                'id' => $item->id, 'type' => $item->type, 'details' => $item->details, 'candidate' => $item->candidate?->lead?->full_name,
            ])->values(),
            'checklists' => RoomOperationChecklist::query()->with('room')->where('status', 'PENDING')->orderBy('created_at')->limit(20)->get()->map(fn (RoomOperationChecklist $item) => [
                'id' => $item->id, 'kind' => $item->kind, 'room' => $item->room?->name, 'items' => $item->items,
            ])->values(),
            'canManage' => $request->user()->hasPermissionTo('shifts.manage_team'),
            'canManageIncidents' => $request->user()->hasPermissionTo('incidents.manage'),
        ]);
    }

    public function storeShift(Request $request): RedirectResponse
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'model_candidate_id' => ['required', 'integer', 'exists:candidates,id'],
            'monitor_candidate_id' => ['nullable', 'integer', 'exists:candidates,id'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $startsAt = Carbon::parse($data['starts_at'], 'America/Bogota')->utc();
        $endsAt = Carbon::parse($data['ends_at'], 'America/Bogota')->utc();

        DB::transaction(function () use ($request, $data, $startsAt, $endsAt): void {
            $model = Candidate::query()->lockForUpdate()->findOrFail($data['model_candidate_id']);
            $this->ensureActiveCandidate($model, CandidateType::MODEL, 'modelo');
            $monitor = ! empty($data['monitor_candidate_id']) ? Candidate::query()->lockForUpdate()->findOrFail($data['monitor_candidate_id']) : null;
            if ($monitor) {
                $this->ensureActiveCandidate($monitor, CandidateType::MONITOR, 'monitor');
                if (! $model->modelAssignments()->where('monitor_candidate_id', $monitor->id)->whereNull('ended_at')->exists()) {
                    throw ValidationException::withMessages(['monitor_candidate_id' => 'El monitor debe estar asignado activamente a esta modelo.']);
                }
            }
            $this->ensureNoShiftOverlap($model->id, $monitor?->id, $startsAt, $endsAt);
            $shift = OperationalShift::create([
                'code' => 'SHIFT-' . Str::upper(Str::random(10)),
                'model_candidate_id' => $model->id,
                'monitor_candidate_id' => $monitor?->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => 'SCHEDULED',
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);
            ShiftAttendance::create(['operational_shift_id' => $shift->id, 'status' => 'PENDING']);

            if (! empty($data['room_id'])) {
                $room = Room::query()->lockForUpdate()->findOrFail($data['room_id']);
                if (! $room->is_active || $room->status !== 'AVAILABLE') throw ValidationException::withMessages(['room_id' => 'La sala no esta disponible.']);
                if (RoomReservation::query()->where('room_id', $room->id)->whereIn('status', ['SCHEDULED', 'IN_USE'])->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt)->exists()) {
                    throw ValidationException::withMessages(['room_id' => 'La sala ya tiene una reserva que se cruza con este turno.']);
                }
                $reservation = RoomReservation::create([
                    'room_id' => $room->id, 'candidate_id' => $model->id, 'reserved_by' => $request->user()->id,
                    'purpose' => "Turno {$shift->code}", 'starts_at' => $startsAt, 'ends_at' => $endsAt, 'status' => 'SCHEDULED', 'notes' => $data['notes'] ?? null,
                ]);
                $shift->update(['room_reservation_id' => $reservation->id]);
                foreach (['OPENING', 'CLOSING'] as $kind) {
                    RoomOperationChecklist::create(['room_id' => $room->id, 'operational_shift_id' => $shift->id, 'kind' => $kind, 'status' => 'PENDING', 'items' => $this->defaultChecklist($kind)]);
                }
            }
        });

        $this->publish('operation.shift.created');
        return back()->with('success', 'Turno programado. La asistencia y la reserva de sala quedan separadas y trazables.');
    }

    public function updateAttendance(Request $request, OperationalShift $shift): RedirectResponse
    {
        $this->authorizeAttendance($request);
        $data = $request->validate(['action' => ['required', Rule::in(['CHECK_IN', 'BREAK_START', 'BREAK_END', 'CHECK_OUT', 'NO_SHOW'])], 'notes' => ['nullable', 'string', 'max:2000']]);
        $sessionService = app(OperationalSessionService::class);
        DB::transaction(function () use ($request, $shift, $data, $sessionService): void {
            $shift = OperationalShift::query()->lockForUpdate()->findOrFail($shift->id);
            $attendance = ShiftAttendance::query()->lockForUpdate()->firstOrCreate(['operational_shift_id' => $shift->id], ['status' => 'PENDING']);
            $now = now('America/Bogota')->utc();
            $attributes = ['updated_by' => $request->user()->id, 'notes' => $data['notes'] ?? $attendance->notes];
            if ($data['action'] === 'CHECK_IN') {
                if ($attendance->checked_in_at) throw ValidationException::withMessages(['action' => 'La entrada ya fue registrada.']);
                $attributes += ['checked_in_at' => $now, 'status' => $now->greaterThan($shift->starts_at) ? 'LATE' : 'ON_TIME', 'late_minutes' => max(0, $shift->starts_at->diffInMinutes($now, false))];
                $shift->update(['status' => 'IN_PROGRESS']);
                $this->syncRoomReservationForShift($shift, 'IN_USE', $now, $request->user()->id);
                $sessionService->startForShift($shift, $request->user()->id, $now);
            }
            if ($data['action'] === 'BREAK_START') {
                if (! $attendance->checked_in_at || $attendance->break_started_at) throw ValidationException::withMessages(['action' => 'La pausa no se puede iniciar en este estado.']);
                $attributes['break_started_at'] = $now;
            }
            if ($data['action'] === 'BREAK_END') {
                if (! $attendance->break_started_at) throw ValidationException::withMessages(['action' => 'No hay una pausa activa.']);
                $attributes['break_minutes'] = $attendance->break_minutes + $attendance->break_started_at->diffInMinutes($now);
                $attributes['break_started_at'] = null;
            }
            if ($data['action'] === 'CHECK_OUT') {
                if (! $attendance->checked_in_at || $attendance->checked_out_at) throw ValidationException::withMessages(['action' => 'La salida no se puede registrar en este estado.']);
                $attributes += ['checked_out_at' => $now, 'status' => $attendance->status === 'LATE' ? 'LATE' : 'COMPLETED'];
                $shift->update(['status' => 'COMPLETED']);
                $this->syncRoomReservationForShift($shift, 'COMPLETED', $now, $request->user()->id);
                $sessionService->finishForShift($shift, $now);
            }
            if ($data['action'] === 'NO_SHOW') {
                if ($attendance->checked_in_at) throw ValidationException::withMessages(['action' => 'No se puede marcar ausencia despues de una entrada.']);
                $attributes['status'] = 'NO_SHOW';
                $shift->update(['status' => 'NO_SHOW']);
            }
            $attendance->update($attributes);
        });
        $this->publish('operation.attendance.updated');
        return back()->with('success', 'Asistencia actualizada.');
    }

    public function completeChecklist(Request $request, RoomOperationChecklist $checklist): RedirectResponse
    {
        $this->authorizeChecklist($request);
        $data = $request->validate(['items' => ['required', 'array', 'min:1'], 'notes' => ['nullable', 'string', 'max:2000']]);
        if (collect($data['items'])->contains(fn ($item) => empty($item['completed']))) throw ValidationException::withMessages(['items' => 'Completa todos los puntos antes de cerrar el checklist.']);
        $checklist->update(['items' => $data['items'], 'notes' => $data['notes'] ?? null, 'status' => 'COMPLETED', 'completed_at' => now(), 'completed_by' => $request->user()->id]);
        return back()->with('success', 'Checklist de sala completado.');
    }

    public function storeIncident(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermissionTo('incidents.create') || $request->user()->hasPermissionTo('incidents.manage'), 403);
        $data = $request->validate(['candidate_id' => ['nullable', 'integer', 'exists:candidates,id'], 'operational_shift_id' => ['nullable', 'integer', 'exists:operational_shifts,id'], 'room_id' => ['nullable', 'integer', 'exists:rooms,id'], 'category' => ['required', 'string', 'max:60'], 'severity' => ['required', Rule::in(['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'])], 'description' => ['required', 'string', 'min:10', 'max:5000']]);
        OperationalIncident::create([...$data, 'reported_by' => $request->user()->id, 'status' => 'OPEN']);
        $this->publish('operation.incident.created');
        return back()->with('success', 'Incidencia registrada.');
    }

    public function resolveIncident(Request $request, OperationalIncident $incident): RedirectResponse
    {
        abort_unless($request->user()->hasPermissionTo('incidents.manage'), 403);
        $data = $request->validate(['status' => ['required', Rule::in(['IN_PROGRESS', 'RESOLVED', 'DISMISSED'])], 'resolution' => ['nullable', 'string', 'max:5000']]);
        $incident->update([...$data, 'resolved_at' => in_array($data['status'], ['RESOLVED', 'DISMISSED'], true) ? now() : null, 'resolved_by' => in_array($data['status'], ['RESOLVED', 'DISMISSED'], true) ? $request->user()->id : null]);
        return back()->with('success', 'Incidencia actualizada.');
    }

    public function storeNote(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermissionTo('models.add_followup') || $request->user()->hasPermissionTo('incidents.manage'), 403);
        $data = $request->validate(['candidate_id' => ['nullable', 'integer', 'exists:candidates,id'], 'operational_shift_id' => ['nullable', 'integer', 'exists:operational_shifts,id'], 'operational_incident_id' => ['nullable', 'integer', 'exists:operational_incidents,id'], 'body' => ['required', 'string', 'min:3', 'max:5000']]);
        OperationalNote::create([...$data, 'author_id' => $request->user()->id]);
        return back()->with('success', 'Novedad operativa registrada.');
    }

    public function storeRequest(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermissionTo('requests.create'), 403);
        $data = $request->validate(['type' => ['required', 'string', 'max:40'], 'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after:starts_at'], 'details' => ['required', 'string', 'min:5', 'max:5000']]);
        OperationalRequest::create([...$data, 'candidate_id' => Candidate::query()->where('user_id', $request->user()->id)->value('id'), 'requested_by' => $request->user()->id, 'status' => 'PENDING']);
        $this->publish('operation.request.created');
        return back()->with('success', 'Solicitud operativa registrada.');
    }

    public function resolveRequest(Request $request, OperationalRequest $operationalRequest): RedirectResponse
    {
        abort_unless($request->user()->hasPermissionTo('incidents.manage'), 403);
        $data = $request->validate(['status' => ['required', Rule::in(['APPROVED', 'REJECTED', 'CANCELLED'])], 'response' => ['nullable', 'string', 'max:5000']]);
        $operationalRequest->update([...$data, 'response' => $data['response'] ?? null, 'resolved_by' => $request->user()->id, 'resolved_at' => now()]);
        return back()->with('success', 'Solicitud actualizada.');
    }

    private function activeCandidates(CandidateType $type)
    {
        return Candidate::query()->with('lead:id,first_name,last_name')->where('candidate_type', $type->value)->where('status', CandidateStatus::ACTIVE->value)->orderBy('id')->get(['id', 'code', 'lead_id'])->map(fn (Candidate $candidate) => ['id' => $candidate->id, 'code' => $candidate->code, 'name' => $candidate->lead?->full_name])->values();
    }
    private function ensureActiveCandidate(Candidate $candidate, CandidateType $type, string $label): void
    {
        if ($candidate->candidate_type !== $type || $candidate->status !== CandidateStatus::ACTIVE) throw ValidationException::withMessages(['candidate' => "El {$label} debe estar activo para programar un turno."]);
    }
    private function ensureNoShiftOverlap(int $modelId, ?int $monitorId, Carbon $startsAt, Carbon $endsAt): void
    {
        $overlap = OperationalShift::query()->whereIn('status', ['SCHEDULED', 'IN_PROGRESS'])->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt);
        if ((clone $overlap)->where('model_candidate_id', $modelId)->exists()) throw ValidationException::withMessages(['model_candidate_id' => 'La modelo ya tiene un turno que se cruza con este horario.']);
        if ($monitorId && $overlap->where('monitor_candidate_id', $monitorId)->exists()) throw ValidationException::withMessages(['monitor_candidate_id' => 'El monitor ya tiene un turno que se cruza con este horario.']);
    }
    private function defaultChecklist(string $kind): array
    {
        return collect($kind === 'OPENING' ? ['Verificar limpieza y equipos', 'Confirmar iluminacion y conectividad', 'Registrar condiciones iniciales'] : ['Verificar estado final de equipos', 'Registrar orden y limpieza', 'Reportar novedades de sala'])->map(fn ($label) => ['label' => $label, 'completed' => false])->all();
    }
    private function shiftPayload(OperationalShift $shift): array
    {
        return ['id' => $shift->id, 'code' => $shift->code, 'status' => $shift->status, 'starts_at' => $shift->starts_at?->timezone('America/Bogota')->toIso8601String(), 'ends_at' => $shift->ends_at?->timezone('America/Bogota')->toIso8601String(), 'model' => $shift->modelCandidate?->lead?->full_name, 'monitor' => $shift->monitorCandidate?->lead?->full_name, 'room' => $shift->roomReservation?->room?->name, 'attendance' => $shift->attendance ? ['status' => $shift->attendance->status, 'checked_in_at' => $shift->attendance->checked_in_at?->timezone('America/Bogota')->toIso8601String(), 'checked_out_at' => $shift->attendance->checked_out_at?->timezone('America/Bogota')->toIso8601String(), 'break_started_at' => $shift->attendance->break_started_at?->timezone('America/Bogota')->toIso8601String(), 'break_minutes' => $shift->attendance->break_minutes, 'late_minutes' => $shift->attendance->late_minutes] : null];
    }

    private function syncRoomReservationForShift(OperationalShift $shift, string $nextStatus, Carbon $at, int $userId): void
    {
        if (! $shift->room_reservation_id) {
            return;
        }

        $reservation = RoomReservation::query()->lockForUpdate()->find($shift->room_reservation_id);
        if (! $reservation) {
            return;
        }

        $previous = $reservation->status;
        if ($nextStatus === 'IN_USE' && $previous === 'SCHEDULED') {
            $reservation->update(['status' => 'IN_USE', 'checked_in_at' => $reservation->checked_in_at ?: $at, 'started_at' => $reservation->started_at ?: $at]);
        } elseif ($nextStatus === 'COMPLETED' && in_array($previous, ['SCHEDULED', 'IN_USE'], true)) {
            $reservation->update(['status' => 'COMPLETED', 'ended_at' => $reservation->ended_at ?: $at]);
        } else {
            return;
        }

        RoomUsageLog::create([
            'room_id' => $reservation->room_id,
            'room_reservation_id' => $reservation->id,
            'changed_by' => $userId,
            'from_status' => $previous,
            'to_status' => $nextStatus,
            'notes' => $nextStatus === 'IN_USE' ? 'Uso iniciado desde la asistencia del turno.' : 'Uso finalizado desde la asistencia del turno.',
        ]);
    }

    private function authorizeView(Request $request): void { abort_unless($request->user()->hasPermissionTo('shifts.view_team') || $request->user()->hasPermissionTo('shifts.manage_team'), 403); }
    private function authorizeManage(Request $request): void { abort_unless($request->user()->hasPermissionTo('shifts.manage_team'), 403); }
    private function authorizeAttendance(Request $request): void { abort_unless($request->user()->hasPermissionTo('attendance.manage'), 403); }
    private function authorizeChecklist(Request $request): void { abort_unless($request->user()->hasPermissionTo('room_checklists.manage'), 403); }
    private function publish(string $event): void { app(RealtimePublisher::class)->publish($event, []); }
}
