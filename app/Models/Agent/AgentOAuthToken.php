<?php

namespace App\Models\Agent;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AgentOAuthToken extends Model
{
    protected $table = 'agent_oauth_tokens';

    protected $guarded = [];

    public function station()
    {
        return $this->belongsTo(AgentStation::class, 'station_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'access_expires_at' => 'datetime',
            'refresh_expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
