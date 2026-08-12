<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutboundEmail extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['queued_at' => 'datetime', 'processing_at' => 'datetime', 'sent_at' => 'datetime', 'failed_at' => 'datetime'];
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function campaignLead()
    {
        return $this->belongsTo(CampaignLead::class);
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function sequenceStep()
    {
        return $this->belongsTo(CampaignSequenceStep::class, 'campaign_sequence_step_id');
    }

    public function sendingAccount()
    {
        return $this->belongsTo(SendingAccount::class);
    }
}
