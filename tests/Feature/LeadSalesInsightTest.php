<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LeadSalesInsightTest extends TestCase
{
    use RefreshDatabase;

    public function test_runtime_insight_uses_website_content_and_openrouter(): void
    {
        Cache::flush();
        config(['ai.openrouter.key' => 'test-key', 'ai.openrouter.model' => 'google/gemini-2.5-flash-lite']);
        Http::fake([
            'https://example.com' => Http::response('<html><head><title>Acme Dental</title><meta name="description" content="Family dental care"></head><body><h1>Book a dentist</h1><p>Appointments and cosmetic dentistry.</p></body></html>'),
            'https://openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => json_encode(['summary' => 'Dental practice', 'best_offer' => 'Appointment website', 'reasons' => ['Booking signal'], 'website_findings' => ['Clear service'], 'opening_pitch' => 'Improve bookings', 'discovery_questions' => ['How are bookings handled?'], 'cautions' => []])]]]]),
        ]);
        $lead = Lead::create(['business_name' => 'Acme', 'website' => 'example.com', 'category' => 'Dentists']);

        $this->actingAs(User::factory()->create())->getJson(route('leads.sales-insight', $lead))
            ->assertOk()->assertJsonPath('website.title', 'Acme Dental')->assertJsonPath('analysis.best_offer', 'Appointment website');
    }

    public function test_lead_without_website_gets_runtime_ai_pitch_starters(): void
    {
        Cache::flush();
        config(['ai.openrouter.key' => 'test-key']);
        $starters = collect(range(1, 10))->map(fn ($number) => "Starter $number")->all();
        Http::fake(['https://openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => json_encode(['summary' => 'No website is recorded.', 'best_offer' => 'New website', 'reasons' => ['Missing website'], 'website_findings' => ['No website available to audit'], 'opening_pitch' => 'Let us build your digital presence.', 'pitch_starters' => $starters, 'discovery_questions' => ['How do customers find you?'], 'cautions' => []])]]]])]);
        $lead = Lead::create(['business_name' => 'Duct Pro', 'category' => 'Duct Cleaning']);

        $this->actingAs(User::factory()->create())->getJson(route('leads.sales-insight', $lead))
            ->assertOk()->assertJsonPath('website.status', 'missing')->assertJsonCount(10, 'analysis.pitch_starters');
        Http::assertSentCount(1);
    }
}
