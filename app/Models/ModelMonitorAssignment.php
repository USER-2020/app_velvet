<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModelMonitorAssignment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function modelCandidate()
    {
        return $this->belongsTo(Candidate::class, 'model_candidate_id');
    }

    public function monitorCandidate()
    {
        return $this->belongsTo(Candidate::class, 'monitor_candidate_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function endedBy()
    {
        return $this->belongsTo(User::class, 'ended_by');
    }
}
