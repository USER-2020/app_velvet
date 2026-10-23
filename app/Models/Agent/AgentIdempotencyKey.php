<?php

namespace App\Models\Agent;

use Illuminate\Database\Eloquent\Model;

class AgentIdempotencyKey extends Model
{
    protected $table = 'agent_idempotency_keys';

    protected $guarded = [];

    protected $hidden = ['response'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }
}
