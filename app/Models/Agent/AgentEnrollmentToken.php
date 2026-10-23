<?php

namespace App\Models\Agent;

use Illuminate\Database\Eloquent\Model;

class AgentEnrollmentToken extends Model
{
    protected $table = 'agent_enrollment_tokens';

    protected $guarded = [];

    protected $hidden = ['token_hash'];

    public function issuer()
    {
        return $this->belongsTo(\App\Models\User::class, 'issued_by');
    }

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime'];
    }
}
