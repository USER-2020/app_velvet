<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LauncherStationAssignment extends Model
{
    protected $table = 'launcher_station_assignments';
    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function station() { return $this->belongsTo(LauncherStation::class, 'station_id'); }
    public function room() { return $this->belongsTo(Room::class); }
    public function modelCandidate() { return $this->belongsTo(Candidate::class, 'model_candidate_id'); }
    public function monitorCandidate() { return $this->belongsTo(Candidate::class, 'monitor_candidate_id'); }
    public function assignedBy() { return $this->belongsTo(User::class, 'assigned_by'); }
}
