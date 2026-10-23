<?php

namespace App\Models\Agent;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AgentWorkSession extends Model
{
    protected $table = 'agent_work_sessions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'transmission_started_at' => 'datetime',
            'ended_at' => 'datetime',
            'last_streaming' => 'boolean',
            'last_obs' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function station()
    {
        return $this->belongsTo(AgentStation::class, 'station_id');
    }
}
