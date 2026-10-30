<?php

namespace App\Http\Controllers;

use App\Enums\CandidateType;
use App\Models\LauncherStation;
use App\Models\LauncherStationAssignment;
use App\Models\LauncherEnrollmentToken;
use App\Models\Candidate;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class StationManagerController extends Controller
{
    public function index()
    {
        $stations = LauncherStation::query()
            ->with(['room', 'currentAssignment.room', 'currentAssignment.modelCandidate.lead', 'currentAssignment.monitorCandidate.lead'])
            ->latest('id')
            ->get()
            ->map(fn (LauncherStation $station) => [
                'id' => $station->id,
                'hostname' => $station->hostname,
                'installationId' => $station->installation_id,
                'studioId' => $station->studio_id,
                'revokedAt' => $station->revoked_at?->toIso8601String(),
                'room' => $station->room?->only(['id', 'code', 'name']),
                'assignment' => $station->currentAssignment ? [
                    'id' => $station->currentAssignment->id,
                    'room' => $station->currentAssignment->room?->only(['id', 'code', 'name']),
                    'model' => $station->currentAssignment->modelCandidate?->lead?->full_name,
                    'monitor' => $station->currentAssignment->monitorCandidate?->lead?->full_name,
                    'startsAt' => $station->currentAssignment->starts_at?->toIso8601String(),
                ] : null,
            ])->values();

        $candidates = Candidate::query()
            ->with('lead:id,first_name,last_name')
            ->where('status', 'ACTIVE')
            ->whereIn('candidate_type', [CandidateType::MODEL->value, CandidateType::MONITOR->value])
            ->get(['id', 'lead_id', 'candidate_type'])
            ->map(fn (Candidate $candidate) => [
                'id' => $candidate->id,
                'type' => $candidate->candidate_type->value,
                'name' => $candidate->lead?->full_name ?: 'Candidato #'.$candidate->id,
            ])->values();

        return Inertia::render('Admin/StationManager/Index', [
            'stations' => $stations,
            'enrollmentCodes' => LauncherEnrollmentToken::query()->with('issuer:id,name')->latest()->limit(15)->get()->map(fn (LauncherEnrollmentToken $token) => [
                'id' => $token->id,
                'studioId' => $token->studio_id,
                'expiresAt' => $token->expires_at?->toIso8601String(),
                'usedAt' => $token->used_at?->toIso8601String(),
                'issuedBy' => $token->issuer?->name,
            ])->values(),
            'rooms' => Room::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'models' => $candidates->where('type', CandidateType::MODEL->value)->values(),
            'monitors' => $candidates->where('type', CandidateType::MONITOR->value)->values(),
            'studios' => config('launcher.studios'),
        ]);
    }

    public function issueEnrollmentCode(Request $request)
    {
        $data = $request->validate(['studio_id' => ['required', 'string', 'max:100', \Illuminate\Validation\Rule::in(config('launcher.studios'))]]);
        $code = 'vle_'.Str::random(48);
        $expires = now()->addMinutes(10);

        LauncherEnrollmentToken::create([
            'token_hash' => hash('sha256', $code),
            'studio_id' => $data['studio_id'],
            'issued_by' => $request->user()->id,
            'expires_at' => $expires,
        ]);

        return back()->with('launcherEnrollmentCode', [
            'code' => $code,
            'studioId' => $data['studio_id'],
            'expiresAt' => $expires->toIso8601String(),
        ]);
    }

    public function assign(Request $request)
    {
        $data = $request->validate([
            'station_id' => ['required', 'integer', 'exists:launcher_stations,id'],
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'model_candidate_id' => ['required', 'integer', 'exists:candidates,id'],
            'monitor_candidate_id' => ['nullable', 'integer', 'exists:candidates,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $startsAt = Carbon::parse($data['starts_at']);
        $endsAt = isset($data['ends_at']) ? Carbon::parse($data['ends_at']) : null;

        $this->ensureQuarterHour($startsAt, 'starts_at');
        if ($endsAt) {
            $this->ensureQuarterHour($endsAt, 'ends_at');
        }

        DB::transaction(function () use ($data, $startsAt, $endsAt, $request): void {
            $station = LauncherStation::query()->lockForUpdate()->findOrFail($data['station_id']);
            abort_if($station->revoked_at, 409, 'No se puede asignar una estación revocada.');

            $room = Room::query()->whereKey($data['room_id'])->where('is_active', true)->first();
            abort_unless($room, 422, 'La room no está activa.');

            $model = Candidate::query()->whereKey($data['model_candidate_id'])->where('candidate_type', CandidateType::MODEL->value)->where('status', 'ACTIVE')->first();
            abort_unless($model, 422, 'La candidata seleccionada no es una modelo activa.');

            $monitor = null;
            if (! empty($data['monitor_candidate_id'])) {
                $monitor = Candidate::query()->whereKey($data['monitor_candidate_id'])->where('candidate_type', CandidateType::MONITOR->value)->where('status', 'ACTIVE')->first();
                abort_unless($monitor, 422, 'El monitor seleccionado no está activo.');
                abort_unless($model->modelAssignments()->where('monitor_candidate_id', $monitor->id)->whereNull('ended_at')->exists(), 422, 'El monitor no está asignado actualmente a esta modelo.');
            }

            $this->ensureNoOverlap('station_id', $station->id, $startsAt, $endsAt);
            $this->ensureNoOverlap('room_id', $room->id, $startsAt, $endsAt);
            $this->ensureNoOverlap('model_candidate_id', $model->id, $startsAt, $endsAt);
            if ($monitor) {
                $this->ensureNoOverlap('monitor_candidate_id', $monitor->id, $startsAt, $endsAt);
            }

            LauncherStationAssignment::create([
                ...$data,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'assigned_by' => $request->user()->id,
                'status' => 'ACTIVE',
            ]);

            $station->update(['room_id' => $room->id]);
        });

        return back()->with('success', 'Asignación de estación creada.');
    }

    public function end(Request $request, LauncherStationAssignment $assignment)
    {
        abort_if($assignment->status !== 'ACTIVE' || $assignment->ends_at, 409, 'La asignación ya está finalizada.');

        $assignment->update(['status' => 'ENDED', 'ends_at' => now()]);

        return back()->with('success', 'Asignación finalizada.');
    }

    private function ensureNoOverlap(string $column, int $value, Carbon $startsAt, ?Carbon $endsAt): void
    {
        $boundary = $endsAt ?: now()->addYears(100);
        $overlap = LauncherStationAssignment::query()
            ->where('status', 'ACTIVE')
            ->where($column, $value)
            ->where('starts_at', '<', $boundary)
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $startsAt))
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([$column => 'Ya existe una asignación activa que se cruza con este horario.']);
        }
    }

    private function ensureQuarterHour(Carbon $value, string $field): void
    {
        if ($value->minute % 15 !== 0 || $value->second !== 0) {
            throw ValidationException::withMessages([$field => 'La hora debe estar en intervalos de 15 minutos.']);
        }
    }
}
