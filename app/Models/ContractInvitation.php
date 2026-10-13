<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractInvitation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'invitation_expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function candidate()
    {
        return $this->belongsTo(Candidate::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
