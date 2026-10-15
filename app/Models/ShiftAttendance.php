<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShiftAttendance extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['checked_in_at' => 'datetime', 'checked_out_at' => 'datetime', 'break_started_at' => 'datetime'];
    }

    public function shift() { return $this->belongsTo(OperationalShift::class, 'operational_shift_id'); }
    public function updatedBy() { return $this->belongsTo(User::class, 'updated_by'); }
}
