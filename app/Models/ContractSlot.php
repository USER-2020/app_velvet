<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractSlot extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function appointment()
    {
        return $this->hasOne(ContractAppointment::class);
    }

    public function appointments()
    {
        return $this->hasMany(ContractAppointment::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
