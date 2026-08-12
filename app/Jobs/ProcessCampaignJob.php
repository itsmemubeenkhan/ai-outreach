<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignLead;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessCampaignJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $campaignId)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $campaign = Campaign::with('sequenceSteps')->find($this->campaignId);
        if (! $campaign || $campaign->status !== 'active' || ($campaign->start_date && $campaign->start_date->isFuture())) {
            return;
        }
        $first = $campaign->sequenceSteps->where('enabled', true)->sortBy('position')->first();
        if (! $first) {
            return;
        }
        $remaining = max(0, $campaign->daily_limit - $campaign->outboundEmails()->where('status', 'sent')->whereBetween('sent_at', [now()->startOfDay(), now()->endOfDay()])->count());
        if (! $remaining) {
            return;
        }
        CampaignLead::where('campaign_id', $campaign->id)->where('status', 'pending')->where('current_step', 0)->whereNull('stopped_at')->where(fn ($q) => $q->whereNull('next_send_at')->orWhere('next_send_at', '<=', now()))->orderBy('id')->limit($remaining)->pluck('id')->each(fn ($id) => SendCampaignEmailJob::dispatch($id, $first->id));
    }
}
