<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudioAccount extends Model
{
    protected $guarded = [];
    protected $hidden = ['encrypted_credentials'];
    protected function casts(): array { return ['credentials_verified_at' => 'datetime', 'last_synced_at' => 'datetime', 'metadata' => 'array']; }
    public function platform() { return $this->belongsTo(PlatformCatalog::class, 'platform_catalog_id'); }
    public function modelAccounts() { return $this->hasMany(ModelPlatformAccount::class); }
}
