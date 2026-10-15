<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationalTrainingRecord extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function candidate()
    {
        return $this->belongsTo(Candidate::class);
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
