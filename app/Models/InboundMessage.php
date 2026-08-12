<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InboundMessage extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['received_at' => 'datetime', 'processed_at' => 'datetime', 'ai_processed_at' => 'datetime', 'reviewed_at' => 'datetime', 'requires_human_action' => 'boolean', 'raw_metadata' => 'array', 'suggested_follow_up_date' => 'date'];
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function campaignLead()
    {
        return $this->belongsTo(CampaignLead::class);
    }

    public function outboundEmail()
    {
        return $this->belongsTo(OutboundEmail::class);
    }

    public function sendingAccount()
    {
        return $this->belongsTo(SendingAccount::class);
    }
}
