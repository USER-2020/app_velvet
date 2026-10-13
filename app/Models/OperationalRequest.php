<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationalRequest extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'resolved_at' => 'datetime']; }
    public function candidate() { return $this->belongsTo(Candidate::class); }
    public function requestedBy() { return $this->belongsTo(User::class, 'requested_by'); }
    public function resolvedBy() { return $this->belongsTo(User::class, 'resolved_by'); }
}
