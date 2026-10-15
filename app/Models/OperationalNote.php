<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationalNote extends Model
{
    protected $guarded = [];
    public function candidate() { return $this->belongsTo(Candidate::class); }
    public function shift() { return $this->belongsTo(OperationalShift::class, 'operational_shift_id'); }
    public function incident() { return $this->belongsTo(OperationalIncident::class, 'operational_incident_id'); }
    public function author() { return $this->belongsTo(User::class, 'author_id'); }
}
