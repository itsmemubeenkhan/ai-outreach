<?php

namespace App\Services;

use App\Models\DialerSession;
use App\Models\Lead;

class DialerSessionService
{
    public function advance(DialerSession $session): void
    {
        $query = Lead::whereNotNull('phone')
            ->where('id', '>', $session->last_lead_id ?? 0)
            ->whereNotIn('lead_status', ['unsubscribed', 'closed']);

        if ($session->category) {
            $query->where('category', $session->category);
        }

        $lead = $query->orderBy('id')->first();

        if (! $lead) {
            $session->update(['status' => 'completed', 'ended_at' => now(), 'current_lead_id' => null]);
            return;
        }

        $session->update(['current_lead_id' => $lead->id, 'last_lead_id' => $lead->id]);
    }
}
