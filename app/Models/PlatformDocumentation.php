<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformDocumentation extends Model
{
    protected $fillable = [
        'created_by', 'title', 'category', 'summary', 'content',
        'attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size',
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
