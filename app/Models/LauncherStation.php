<?php

namespace App\Models;

class LauncherStation extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'launcher_stations';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'configuration' => 'array',
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function assignments()
    {
        return $this->hasMany(LauncherStationAssignment::class, 'station_id');
    }

    public function currentAssignment()
    {
        return $this->hasOne(LauncherStationAssignment::class, 'station_id')
            ->where('status', 'ACTIVE')
            ->where(fn ($query) => $query
                ->whereNull('ends_at')
                ->orWhere('ends_at', '>', now()))
            ->latestOfMany('starts_at');
    }
}
