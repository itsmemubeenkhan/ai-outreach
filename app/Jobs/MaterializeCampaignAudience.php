<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignLead;
use App\Models\Lead;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class MaterializeCampaignAudience implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1200;

    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $campaignId) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $campaign = Campaign::findOrFail($this->campaignId);
        $campaign->update(['audience_status' => 'building', 'audience_count' => 0]);
        CampaignLead::where('campaign_id', $campaign->id)->where('status', 'pending')->delete();
        $filters = array_filter($campaign->audience_filters ?? [], fn ($value) => $value !== null && $value !== '');
        $query = Lead::query()->select('id');
        foreach (['category', 'country', 'state', 'city', 'email_status', 'phone_type', 'lead_status', 'source'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (($filters['email_availability'] ?? null) === 'yes') {
            $query->whereNotNull('email');
        }
        if (($filters['email_availability'] ?? null) === 'no') {
            $query->whereNull('email');
        }
        if (($filters['website_availability'] ?? null) === 'yes') {
            $query->whereNotNull('website');
        }
        if (($filters['website_availability'] ?? null) === 'no') {
            $query->whereNull('website');
        }
        $count = 0;
        $query->orderBy('id')->chunkById(1000, function ($leads) use ($campaign, &$count) {
            $now = now();
            $rows = $leads->map(fn ($lead) => ['campaign_id' => $campaign->id, 'lead_id' => $lead->id, 'status' => 'pending', 'current_step' => 0, 'created_at' => $now, 'updated_at' => $now])->all();
            $count += CampaignLead::insertOrIgnore($rows);
            $campaign->update(['audience_count' => $count]);
        });
        $campaign->update(['audience_status' => 'ready', 'audience_count' => $count]);
    }

    public function failed(\Throwable $e): void
    {
        Campaign::whereKey($this->campaignId)->update(['audience_status' => 'failed']);
    }
}
