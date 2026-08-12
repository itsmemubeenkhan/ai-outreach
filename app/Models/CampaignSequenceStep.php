<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignSequenceStep extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function outboundEmails()
    {
        return $this->hasMany(OutboundEmail::class);
    }
}
