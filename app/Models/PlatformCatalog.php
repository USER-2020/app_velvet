<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformCatalog extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['is_active' => 'boolean', 'metadata' => 'array']; }
    public function studioAccounts() { return $this->hasMany(StudioAccount::class); }
}
