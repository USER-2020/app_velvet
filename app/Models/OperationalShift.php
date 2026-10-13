<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationalShift extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function modelCandidate() { return $this->belongsTo(Candidate::class, 'model_candidate_id'); }
    public function monitorCandidate() { return $this->belongsTo(Candidate::class, 'monitor_candidate_id'); }
    public function roomReservation() { return $this->belongsTo(RoomReservation::class); }
    public function attendance() { return $this->hasOne(ShiftAttendance::class); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
}
