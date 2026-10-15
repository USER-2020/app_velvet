<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationalIncident extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['resolved_at' => 'datetime']; }
    public function candidate() { return $this->belongsTo(Candidate::class); }
    public function shift() { return $this->belongsTo(OperationalShift::class, 'operational_shift_id'); }
    public function room() { return $this->belongsTo(Room::class); }
    public function reportedBy() { return $this->belongsTo(User::class, 'reported_by'); }
    public function resolvedBy() { return $this->belongsTo(User::class, 'resolved_by'); }
}
