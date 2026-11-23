<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WishlistItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['target_date' => 'date', 'estimated_price' => 'decimal:2', 'metadata' => 'array'];
    }

    public function candidate()
    {
        return $this->belongsTo(Candidate::class);
    }
}
