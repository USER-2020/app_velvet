<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModelProjection extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['target_date' => 'date', 'target_amount' => 'decimal:2', 'mind_map' => 'array', 'metadata' => 'array'];
    }

    public function candidate()
    {
        return $this->belongsTo(Candidate::class);
    }
}
