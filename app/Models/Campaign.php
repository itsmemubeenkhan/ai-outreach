<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['audience_filters' => 'array', 'start_date' => 'date'];
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function sequenceSteps()
    {
        return $this->hasMany(CampaignSequenceStep::class)->orderBy('position');
    }

    public function campaignLeads()
    {
        return $this->hasMany(CampaignLead::class);
    }

    public function leads()
    {
        return $this->belongsToMany(Lead::class)->withPivot(['status', 'current_step', 'next_send_at', 'stopped_at', 'stop_reason'])->withTimestamps();
    }

    public function sendingAccounts()
    {
        return $this->belongsToMany(SendingAccount::class)->withTimestamps();
    }

    public function outboundEmails()
    {
        return $this->hasMany(OutboundEmail::class);
    }
}
