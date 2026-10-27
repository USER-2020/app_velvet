<?php

namespace App\Models\Agent;

use Illuminate\Database\Eloquent\Model;

class AgentStation extends Model
{
    protected $table = 'agent_stations';

    protected $guarded = [];

    protected $hidden = ['credential_hash'];

    protected function casts(): array
    {
        return ['abilities' => 'array', 'configuration' => 'array', 'obs_status' => 'array', 'system_metrics' => 'array', 'last_seen_at' => 'datetime', 'revoked_at' => 'datetime', 'offline_at' => 'datetime', 'obs_reported_at' => 'datetime'];
    }
}
