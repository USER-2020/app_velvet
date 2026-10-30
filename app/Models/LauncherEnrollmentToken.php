<?php

namespace App\Models;

class LauncherEnrollmentToken extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'launcher_enrollment_tokens';
    protected $guarded = [];
    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime'];
    }

    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
