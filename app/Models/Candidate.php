<?php

namespace App\Models;

use App\Enums\CandidateStatus;
use App\Enums\CandidateType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Candidate extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => CandidateStatus::class,
            'candidate_type' => CandidateType::class,
            'prequalification_data' => 'array',
            'admitted_at' => 'datetime',
            'activated_at' => 'datetime',
            'prequalification_sent_at' => 'datetime',
            'prequalification_completed_at' => 'datetime',
            'prequalification_expires_at' => 'datetime',
            'identity_verification_data' => 'array',
            'identity_verified_at' => 'datetime',
        ];
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function activities()
    {
        return $this->hasMany(CandidateActivity::class);
    }

    public function interviews()
    {
        return $this->hasMany(Interview::class);
    }

    public function contractAppointments()
    {
        return $this->hasMany(ContractAppointment::class);
    }

    public function contractInvitations()
    {
        return $this->hasMany(ContractInvitation::class);
    }

    public function operationalProfile()
    {
        return $this->hasOne(OperationalProfile::class);
    }

    public function trainingRecords()
    {
        return $this->hasMany(OperationalTrainingRecord::class);
    }

    public function modelAssignments()
    {
        return $this->hasMany(ModelMonitorAssignment::class, 'model_candidate_id');
    }

    public function monitorAssignments()
    {
        return $this->hasMany(ModelMonitorAssignment::class, 'monitor_candidate_id');
    }

    public function currentMonitorAssignment()
    {
        return $this->hasOne(ModelMonitorAssignment::class, 'model_candidate_id')
            ->whereNull('ended_at')
            ->latestOfMany('assigned_at');
    }

    public function stationAssignmentsAsModel()
    {
        return $this->hasMany(StationAssignment::class, 'model_candidate_id');
    }

    public function stationAssignmentsAsMonitor()
    {
        return $this->hasMany(StationAssignment::class, 'monitor_candidate_id');
    }
}
