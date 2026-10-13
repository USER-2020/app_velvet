<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationalProfile extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'onboarding_completed_at' => 'datetime',
            'induction_completed_at' => 'datetime',
            'activated_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function candidate()
    {
        return $this->belongsTo(Candidate::class);
    }

    public function activatedBy()
    {
        return $this->belongsTo(User::class, 'activated_by');
    }
}
