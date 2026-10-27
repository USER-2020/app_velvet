<?php

namespace App\Models\Agent;

use Illuminate\Database\Eloquent\Model;

class AgentCommand extends Model
{
    protected $table = 'agent_commands';

    protected $guarded = [];

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'result' => 'array'];
    }
}
