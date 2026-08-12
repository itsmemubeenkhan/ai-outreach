<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['last_contacted_at' => 'datetime', 'next_follow_up_at' => 'datetime'];
    }

    public function outboundEmails()
    {
        return $this->hasMany(OutboundEmail::class);
    }

    public function activities()
    {
        return $this->hasMany(LeadActivity::class)->latest();
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function callRecords()
    {
        return $this->hasMany(CallRecord::class);
    }
}
