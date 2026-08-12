<?php

namespace Tests\Feature;

use App\Models\DialerSession;
use App\Models\CallRecord;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PowerDialerTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_session_advances_and_records_disposition(): void
    {
        $user = User::factory()->create();
        $first = Lead::create(['business_name' => 'Dental A', 'phone' => '+15550000001', 'category' => 'Dentists']);
        Lead::create(['business_name' => 'Dental B', 'phone' => '+15550000002', 'category' => 'Dentists']);
        Lead::create(['business_name' => 'Other', 'phone' => '+15550000003', 'category' => 'Real Estate']);
        $this->actingAs($user)->post(route('dialer.start'), ['category' => 'Dentists', 'auto_next_delay' => 5])->assertRedirect(route('dialer.index'));
        $session = DialerSession::first();
        $this->assertSame($first->id, $session->current_lead_id);
        $this->assertSame(30, $session->auto_next_delay);
        $response = $this->actingAs($user)->postJson(route('dialer.dial', $session));
        $response->assertOk()->assertJsonPath('dial_url', 'zoomphonecall://+15550000001');
        $callId = $response->json('call_id');
        $this->actingAs($user)->post(route('dialer.disposition', $callId), ['disposition' => 'answered', 'notes' => 'Spoke'])->assertRedirect();
        $this->assertNotSame($first->id, $session->refresh()->current_lead_id);
        $this->assertDatabaseHas('call_records', ['id' => $callId, 'disposition' => 'answered']);
    }

    public function test_user_cannot_control_another_users_session(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $session = DialerSession::create(['user_id' => $owner->id, 'status' => 'active']);
        $this->actingAs($other)->post(route('dialer.control', $session), ['action' => 'stop'])->assertForbidden();
    }

    public function test_category_picker_shows_only_primary_category(): void
    {
        $user = User::factory()->create();
        Lead::create(['business_name' => 'Duct Pro', 'phone' => '+15550000001', 'category' => 'Duct Cleaning,Home Services,HVAC']);

        $this->actingAs($user)->get(route('dialer.index'))->assertOk()->assertSee('Duct Cleaning')->assertDontSee('Duct Cleaning,Home Services,HVAC');
    }

    public function test_signed_zoom_completion_advances_the_active_session(): void
    {
        config(['zoom.webhook_secret' => 'test-secret']);
        $user = User::factory()->create();
        $first = Lead::create(['business_name' => 'First', 'phone' => '+15550000001', 'category' => 'Dentists']);
        $second = Lead::create(['business_name' => 'Second', 'phone' => '+15550000002', 'category' => 'Dentists']);
        $session = DialerSession::create(['user_id' => $user->id, 'category' => 'Dentists', 'status' => 'active', 'current_lead_id' => $first->id, 'last_lead_id' => $first->id]);
        $call = CallRecord::create(['dialer_session_id' => $session->id, 'lead_id' => $first->id, 'user_id' => $user->id, 'uuid' => fake()->uuid(), 'phone_number' => $first->phone, 'status' => 'dialing']);
        $payload = json_encode(['event' => 'phone.caller_call_element_completed', 'payload' => ['object' => ['call_element' => ['call_id' => 'zoom-call-1', 'callee_number' => $first->phone, 'duration' => 12]]]], JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;
        $signature = 'v0='.hash_hmac('sha256', "v0:$timestamp:$payload", 'test-secret');

        $this->call('POST', route('zoom.webhook'), [], [], [], ['HTTP_X_ZM_REQUEST_TIMESTAMP' => $timestamp, 'HTTP_X_ZM_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'], $payload)->assertOk();

        $this->assertSame('completed', $call->refresh()->status);
        $this->assertSame($second->id, $session->refresh()->current_lead_id);
        $this->assertSame(1, $session->calls_completed);
    }
}
