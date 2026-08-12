<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_and_filter_leads(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/leads', [
            'business_name' => 'Acme Dental', 'email' => 'Owner@Example.com', 'category' => 'Dentists',
            'email_status' => 'valid', 'lead_status' => 'new', 'lead_score' => 0,
        ])->assertRedirect('/leads');

        $this->assertDatabaseHas('leads', ['email' => 'owner@example.com', 'category' => 'Dentists']);
        $this->actingAs($user)->get('/leads?category=Dentists')->assertOk()->assertSee('Acme Dental');
    }

    public function test_lead_email_must_be_unique(): void
    {
        $user = User::factory()->create();
        Lead::create(['email' => 'same@example.com']);
        $this->actingAs($user)->post('/leads', ['email' => 'same@example.com', 'email_status' => 'unknown', 'lead_status' => 'new'])->assertSessionHasErrors('email');
    }
}
