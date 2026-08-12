<?php

namespace Tests\Feature;

use App\Jobs\MaterializeCampaignAudience;
use App\Models\Brand;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CampaignManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_is_created_with_filter_defined_audience(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $brand = Brand::create(['name' => 'The Brand Maker', 'slug' => 'the-brand-maker']);
        $response = $this->actingAs($user)->post('/campaigns', ['brand_id' => $brand->id, 'name' => 'Dentists TX', 'status' => 'draft', 'daily_limit' => 50, 'sender_strategy' => 'round_robin', 'audience' => ['category' => 'Dentists', 'state' => 'TX', 'email_availability' => 'yes']]);
        $campaign = Campaign::first();
        $response->assertRedirect(route('campaigns.show', $campaign));
        $this->assertSame('Dentists', $campaign->audience_filters['category']);
        Queue::assertPushed(MaterializeCampaignAudience::class);
    }

    public function test_audience_job_materializes_only_matching_leads(): void
    {
        $brand = Brand::create(['name' => 'Aspire Website Designs', 'slug' => 'aspire']);
        $campaign = Campaign::create(['brand_id' => $brand->id, 'name' => 'Austin', 'status' => 'draft', 'daily_limit' => 10, 'sender_strategy' => 'round_robin', 'audience_filters' => ['city' => 'Austin', 'email_availability' => 'yes']]);
        $match = Lead::create(['email' => 'match@example.com', 'city' => 'Austin']);
        Lead::create(['email' => 'other@example.com', 'city' => 'Dallas']);
        Lead::create(['city' => 'Austin']);
        (new MaterializeCampaignAudience($campaign->id))->handle();
        $this->assertDatabaseHas('campaign_leads', ['campaign_id' => $campaign->id, 'lead_id' => $match->id]);
        $this->assertSame(1, $campaign->refresh()->audience_count);
        $this->assertSame('ready', $campaign->audience_status);
    }

    public function test_sequence_step_supports_template_variables(): void
    {
        $user = User::factory()->create();
        $brand = Brand::create(['name' => 'Brand', 'slug' => 'brand']);
        $campaign = Campaign::create(['brand_id' => $brand->id, 'name' => 'Campaign', 'status' => 'draft', 'daily_limit' => 10, 'sender_strategy' => 'round_robin']);
        $this->actingAs($user)->post(route('campaigns.steps.store', $campaign), ['subject' => 'Hello {{first_name}}', 'body' => 'A note for {{business_name}}', 'delay_days' => 0, 'position' => 1, 'enabled' => 1])->assertRedirect();
        $this->assertDatabaseHas('campaign_sequence_steps', ['campaign_id' => $campaign->id, 'position' => 1, 'enabled' => 1]);
    }
}
