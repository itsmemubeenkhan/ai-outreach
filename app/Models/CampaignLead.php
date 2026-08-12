<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignLead extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['next_send_at' => 'datetime', 'stopped_at' => 'datetime', 'replied_at' => 'datetime'];
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function outboundEmails()
    {
        return $this->hasMany(OutboundEmail::class);
    }
}
