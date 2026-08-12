<?php

namespace Tests\Feature;

use App\Models\DialerSession;
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
}
