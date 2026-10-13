<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\OperationalShift;
use App\Models\RoomReservation;
use App\Models\ShiftAttendance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class OperationalSessionService
{
    public function startForReservation(RoomReservation $reservation, int $userId, ?int $monitorCandidateId = null, ?Carbon $startedAt = null): void
    {
        $shift = OperationalShift::query()
            ->where('room_reservation_id', $reservation->id)
            ->lockForUpdate()
            ->first();

        if ($shift) {
            $this->startForShift($shift, $userId, $startedAt, $monitorCandidateId);
            return;
        }

        $monitorCandidateId ??= Candidate::query()
            ->where('user_id', $userId)
            ->where('candidate_type', 'MONITOR')
            ->value('id');
        $startedAt ??= now('America/Bogota')->utc();

        $session = DB::table('operational_sessions')
            ->where('room_reservation_id', $reservation->id)
            ->lockForUpdate()
            ->first();

        if ($session) {
            return;
        }

        DB::table('operational_sessions')->insert([
            'room_reservation_id' => $reservation->id,
            'candidate_id' => $reservation->candidate_id,
            'monitor_candidate_id' => $monitorCandidateId,
            'status' => 'OPEN',
            'started_at' => $startedAt,
            'source' => 'MANUAL',
            'notes' => "Sesión iniciada desde la reserva de room #{$reservation->id}.",
            'opened_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function finishForReservation(RoomReservation $reservation, ?Carbon $endedAt = null): void
    {
        $shift = OperationalShift::query()
            ->where('room_reservation_id', $reservation->id)
            ->lockForUpdate()
            ->first();

        if ($shift) {
            $this->finishForShift($shift, $endedAt);
            return;
        }

        $this->finishWhere(['room_reservation_id' => $reservation->id], $endedAt);
    }

    public function startForShift(OperationalShift $shift, int $userId, ?Carbon $startedAt = null, ?int $monitorCandidateId = null): void
    {
        $startedAt ??= now('America/Bogota')->utc();
        $monitorCandidateId ??= $shift->monitor_candidate_id;

        $attendance = ShiftAttendance::query()->lockForUpdate()->firstOrCreate(
            ['operational_shift_id' => $shift->id],
            ['status' => 'PENDING'],
        );
        if (! $attendance->checked_in_at) {
            $attendance->update([
                'checked_in_at' => $startedAt,
                'status' => $startedAt->greaterThan($shift->starts_at) ? 'LATE' : 'ON_TIME',
                'late_minutes' => max(0, $shift->starts_at->diffInMinutes($startedAt, false)),
                'updated_by' => $userId,
            ]);
        }
        if ($shift->status === 'SCHEDULED') {
            $shift->update(['status' => 'IN_PROGRESS']);
        }

        $session = DB::table('operational_sessions')
            ->where('operational_shift_id', $shift->id)
            ->lockForUpdate()
            ->first();

        if ($session) {
            return;
        }

        DB::table('operational_sessions')->insert([
            'operational_shift_id' => $shift->id,
            'room_reservation_id' => $shift->room_reservation_id,
            'candidate_id' => $shift->model_candidate_id,
            'monitor_candidate_id' => $monitorCandidateId,
            'status' => 'OPEN',
            'started_at' => $startedAt,
            'source' => 'MANUAL',
            'notes' => "Sesión iniciada desde el turno {$shift->code}.",
            'opened_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function finishForShift(OperationalShift $shift, ?Carbon $endedAt = null): void
    {
        $endedAt ??= now('America/Bogota')->utc();
        $attendance = ShiftAttendance::query()->lockForUpdate()->where('operational_shift_id', $shift->id)->first();
        if ($attendance?->checked_in_at && ! $attendance->checked_out_at) {
            $attendance->update([
                'checked_out_at' => $endedAt,
                'status' => $attendance->status === 'LATE' ? 'LATE' : 'COMPLETED',
            ]);
        }
        if (in_array($shift->status, ['SCHEDULED', 'IN_PROGRESS'], true)) {
            $shift->update(['status' => 'COMPLETED']);
        }
        $this->finishWhere(['operational_shift_id' => $shift->id], $endedAt);
    }

    private function finishWhere(array $where, ?Carbon $endedAt = null): void
    {
        $endedAt ??= now('America/Bogota')->utc();
        DB::table('operational_sessions')
            ->where($where)
            ->where('status', 'OPEN')
            ->update(['status' => 'COMPLETED', 'ended_at' => $endedAt, 'updated_at' => now()]);
    }
}
