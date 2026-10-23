<?php

namespace App\Models\Agent;

use Illuminate\Database\Eloquent\Model;

class AgentOAuthAuthorizationCode extends Model
{
    protected $table = 'agent_oauth_authorization_codes';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime'];
    }
}
