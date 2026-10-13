<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomOperationChecklist extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['items' => 'array', 'completed_at' => 'datetime']; }
    public function room() { return $this->belongsTo(Room::class); }
    public function shift() { return $this->belongsTo(OperationalShift::class, 'operational_shift_id'); }
    public function completedBy() { return $this->belongsTo(User::class, 'completed_by'); }
}
