<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DialerRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_dialer_user_only_sees_and_accesses_leads_and_power_dialer(): void
    {
        $user = User::factory()->create(['role' => 'dialer']);

        $this->actingAs($user)->get(route('leads.index'))->assertOk()->assertSee('Leads')->assertSee('Power Dialer')->assertDontSee('Campaigns');
        $this->actingAs($user)->get(route('dialer.index'))->assertOk();
        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('dialer.index'));
        $this->actingAs($user)->get(route('campaigns.index'))->assertForbidden();
        $this->actingAs($user)->get(route('imports.index'))->assertForbidden();
    }

    public function test_dialer_login_ignores_an_admin_intended_url(): void
    {
        $user = User::factory()->create(['email' => 'dialer@example.com', 'password' => 'password', 'role' => 'dialer']);

        $this->withSession(['url.intended' => route('dashboard')])->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dialer.index'));
    }
}
