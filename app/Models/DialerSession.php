<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DialerSession extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['filters' => 'array', 'started_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function currentLead()
    {
        return $this->belongsTo(Lead::class, 'current_lead_id');
    }

    public function callRecords()
    {
        return $this->hasMany(CallRecord::class);
    }
}
