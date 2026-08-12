<?php

namespace App\Jobs;

use App\Models\CampaignLead;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessFollowUpsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        CampaignLead::with(['campaign.sequenceSteps'])->where('status', 'pending')->where('current_step', '>', 0)->whereNull('stopped_at')->whereNotNull('next_send_at')->where('next_send_at', '<=', now())->orderBy('id')->chunkById(500, function ($campaignLeads) {
            foreach ($campaignLeads as $campaignLead) {
                if ($campaignLead->campaign->status !== 'active') {
                    continue;
                } $step = $campaignLead->campaign->sequenceSteps->where('enabled', true)->where('position', '>', $campaignLead->current_step)->sortBy('position')->first();
                if ($step) {
                    SendCampaignEmailJob::dispatch($campaignLead->id, $step->id);
                }
            }
        });
    }
}
