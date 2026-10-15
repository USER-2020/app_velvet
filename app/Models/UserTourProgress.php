<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserTourProgress extends Model
{
    protected $table = 'user_tour_progresses';

    protected $fillable = [
        'user_id',
        'tour_key',
        'version',
        'completed_at',
        'skipped_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'skipped_at' => 'datetime',
        ];
    }
}
