<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use Illuminate\Database\Eloquent\Model;

class RoomReservation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'starts_at' => UtcDateTime::class,
            'ends_at' => UtcDateTime::class,
            'checked_in_at' => UtcDateTime::class,
            'started_at' => UtcDateTime::class,
            'ended_at' => UtcDateTime::class,
        ];
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function candidate()
    {
        return $this->belongsTo(Candidate::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reservedBy()
    {
        return $this->belongsTo(User::class, 'reserved_by');
    }

    public function usageLogs()
    {
        return $this->hasMany(RoomUsageLog::class);
    }
}
