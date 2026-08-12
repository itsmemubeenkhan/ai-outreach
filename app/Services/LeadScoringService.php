<?php

namespace App\Services;

use App\Models\InboundMessage;
use App\Models\LeadActivity;
use Illuminate\Support\Facades\DB;

class LeadScoringService
{
    public function applyReply(InboundMessage $message): void
    {
        DB::transaction(function () use ($message) {
            if (! $message->lead_id) {
                return;
            }$key = 'reply_scored';
            if (LeadActivity::where('inbound_message_id', $message->id)->where('type', $key)->exists()) {
                return;
            }$scores = config('outreach.scoring');
            $points = ($scores['reply'] ?? 30) + ($scores[$message->classification] ?? 0);
            $message->lead()->increment('lead_score', $points);
            LeadActivity::create(['lead_id' => $message->lead_id, 'inbound_message_id' => $message->id, 'type' => $key, 'description' => 'Reply scored '.$points.' points', 'metadata' => ['points' => $points]]);
        });
    }
}
