<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModelPlatformAccount extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['is_authorized' => 'boolean', 'linked_at' => 'datetime', 'unlinked_at' => 'datetime']; }
    public function candidate() { return $this->belongsTo(Candidate::class); }
    public function studioAccount() { return $this->belongsTo(StudioAccount::class); }
}
