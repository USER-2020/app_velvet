<?php

namespace App\Models;

use App\Models\Agent\AgentStation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function reservations()
    {
        return $this->hasMany(RoomReservation::class);
    }

    public function usageLogs()
    {
        return $this->hasMany(RoomUsageLog::class);
    }

    public function agentStations()
    {
        return $this->hasMany(AgentStation::class);
    }

    public function launcherStations()
    {
        return $this->hasMany(LauncherStation::class);
    }

    public function stationAssignments()
    {
        return $this->hasMany(StationAssignment::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
