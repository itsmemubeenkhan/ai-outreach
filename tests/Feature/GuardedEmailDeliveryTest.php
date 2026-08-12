<?php

namespace Tests\Feature;

use App\Contracts\OutboundTransport;
use App\Jobs\SendCampaignEmailJob;
use App\Models\Brand;
use App\Models\Campaign;
use App\Models\CampaignLead;
use App\Models\CampaignSequenceStep;
use App\Models\Lead;
use App\Models\OutboundEmail;
use App\Models\SendingAccount;
use App\Models\Suppression;
use App\Models\User;
use App\Services\RoundRobinSenderSelector;
use App\Services\TemplateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class GuardedEmailDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_sending_account_passwords_are_encrypted_and_blank_edit_preserves_them(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);
        $raw = DB::table('sending_accounts')->where('id', $account->id)->first();
        $this->assertNotSame('smtp-secret', $raw->smtp_password);
        $this->assertNotSame('imap-secret', $raw->imap_password);
        $this->actingAs($user)->put(route('sending-accounts.update', $account), $this->accountPayload(['smtp_password' => '', 'imap_password' => '']))->assertRedirect();
        $this->assertSame('smtp-secret', $account->refresh()->smtp_password);
        $this->assertArrayNotHasKey('smtp_password', $account->toArray());
    }

    public function test_suppression_unsubscribe_paused_and_inactive_sender_prevent_sending(): void
    {
        foreach (['suppressed', 'unsubscribed', 'paused', 'inactive_sender'] as $case) {
            [$campaignLead,$step,$account] = $this->scenario();
            if ($case === 'suppressed') {
                Suppression::create(['email' => $campaignLead->lead->email, 'reason' => 'manual']);
            }if ($case === 'unsubscribed') {
                $campaignLead->lead->update(['lead_status' => 'unsubscribed']);
            }if ($case === 'paused') {
                $campaignLead->campaign->update(['status' => 'paused']);
            }if ($case === 'inactive_sender') {
                $account->update(['status' => 'paused']);
            }$transport = new FakeOutboundTransport;
            $this->runJob($campaignLead, $step, $transport);
            $this->assertSame(0, $transport->sent);
        }
    }

    public function test_daily_limit_and_round_robin_skip_full_accounts(): void
    {
        [$campaignLead,$step,$first] = $this->scenario(['daily_limit' => 1]);
        $second = $this->account($first->owner, ['email' => 'second@example.com']);
        $campaignLead->campaign->sendingAccounts()->attach($second);
        OutboundEmail::create($this->outboundPayload($campaignLead, $step, $first, ['status' => 'sent', 'sent_at' => now()]));
        $selected = (new RoundRobinSenderSelector)->select($campaignLead->campaign);
        $this->assertSame($second->id, $selected->id);
        $second->update(['daily_limit' => 0]);
        $this->assertNull((new RoundRobinSenderSelector)->select($campaignLead->campaign));
    }

    public function test_success_is_logged_and_duplicate_job_does_not_send_twice(): void
    {
        [$campaignLead,$step] = $this->scenario();
        $transport = new FakeOutboundTransport;
        $this->runJob($campaignLead, $step, $transport);
        $this->runJob($campaignLead, $step, $transport);
        $this->assertSame(1, $transport->sent);
        $this->assertDatabaseHas('outbound_emails', ['campaign_lead_id' => $campaignLead->id, 'campaign_sequence_step_id' => $step->id, 'status' => 'sent', 'attempt_count' => 1]);
    }

    public function test_failed_transport_records_safe_failure(): void
    {
        [$campaignLead,$step] = $this->scenario();
        $transport = new FakeOutboundTransport(throw: true);
        $this->runJob($campaignLead, $step, $transport);
        $this->assertDatabaseHas('outbound_emails', ['campaign_lead_id' => $campaignLead->id, 'status' => 'failed', 'attempt_count' => 1]);
    }

    public function test_follow_up_cannot_send_early_or_after_stop(): void
    {
        [$campaignLead,$first] = $this->scenario();
        $second = CampaignSequenceStep::create(['campaign_id' => $campaignLead->campaign_id, 'subject' => 'Follow up', 'body' => 'Again', 'delay_days' => 3, 'position' => 2, 'enabled' => true]);
        $campaignLead->update(['current_step' => 1, 'next_send_at' => now()->addDay()]);
        OutboundEmail::create($this->outboundPayload($campaignLead, $first, $campaignLead->campaign->sendingAccounts->first(), ['status' => 'sent', 'sent_at' => now()]));
        $transport = new FakeOutboundTransport;
        $this->runJob($campaignLead->fresh(), $second, $transport);
        $this->assertSame(0, $transport->sent);
        $campaignLead->update(['next_send_at' => now()->subMinute(), 'status' => 'stopped', 'stopped_at' => now(), 'stop_reason' => 'replied']);
        $this->runJob($campaignLead->fresh(), $second, $transport);
        $this->assertSame(0, $transport->sent);
    }

    public function test_signed_unsubscribe_suppresses_and_stops_future_sends(): void
    {
        [$campaignLead,$step,$account] = $this->scenario();
        $outbound = OutboundEmail::create($this->outboundPayload($campaignLead, $step, $account, ['status' => 'sent', 'sent_at' => now()]));
        $url = URL::signedRoute('unsubscribe', ['message' => $outbound->message_uuid]);
        $this->get($url)->assertOk()->assertSee('unsubscribed');
        $this->assertDatabaseHas('suppressions', ['email' => $campaignLead->lead->email, 'reason' => 'unsubscribe']);
        $this->assertSame('unsubscribed', $campaignLead->lead->refresh()->lead_status);
        $this->assertSame('stopped', $campaignLead->refresh()->status);
    }

    private function scenario(array $accountOverrides = []): array
    {
        static $counter = 0;
        $counter++;
        $user = User::factory()->create();
        $brand = Brand::create(['name' => 'Brand '.$counter, 'slug' => 'brand-'.$counter]);
        $campaign = Campaign::create(['brand_id' => $brand->id, 'name' => 'Campaign', 'status' => 'active', 'daily_limit' => 50, 'sender_strategy' => 'round_robin', 'audience_status' => 'ready']);
        $lead = Lead::create(['business_name' => 'Acme', 'first_name' => 'Alex', 'email' => 'lead'.$counter.'@example.com', 'email_status' => 'valid', 'lead_status' => 'new']);
        $campaignLead = CampaignLead::create(['campaign_id' => $campaign->id, 'lead_id' => $lead->id, 'status' => 'pending']);
        $step = CampaignSequenceStep::create(['campaign_id' => $campaign->id, 'subject' => 'Hello {{first_name}}', 'body' => 'For {{business_name}}', 'delay_days' => 0, 'position' => 1, 'enabled' => true]);
        $account = $this->account($user, array_merge(['email' => 'sender'.$counter.'@example.com'], $accountOverrides));
        $campaign->sendingAccounts()->attach($account);

        return [$campaignLead->load(['campaign', 'lead']), $step, $account];
    }

    private function account(User $user, array $overrides = []): SendingAccount
    {
        return SendingAccount::create(array_merge($this->accountPayload(), ['user_id' => $user->id], $overrides));
    }

    private function accountPayload(array $overrides = []): array
    {
        return array_merge(['name' => 'Mailbox', 'sender_name' => 'Sender', 'email' => 'sender@example.com', 'smtp_host' => 'smtp.example.com', 'smtp_port' => 587, 'smtp_username' => 'sender', 'smtp_password' => 'smtp-secret', 'smtp_encryption' => 'tls', 'imap_host' => 'imap.example.com', 'imap_port' => 993, 'imap_username' => 'sender', 'imap_password' => 'imap-secret', 'imap_encryption' => 'ssl', 'daily_limit' => 50, 'status' => 'active'], $overrides);
    }

    private function outboundPayload(CampaignLead $campaignLead, CampaignSequenceStep $step, SendingAccount $account, array $overrides = []): array
    {
        return array_merge(['campaign_id' => $campaignLead->campaign_id, 'campaign_lead_id' => $campaignLead->id, 'lead_id' => $campaignLead->lead_id, 'campaign_sequence_step_id' => $step->id, 'sending_account_id' => $account->id, 'message_uuid' => (string) Str::uuid(), 'subject' => $step->subject, 'recipient_email' => $campaignLead->lead->email, 'status' => 'queued', 'queued_at' => now()], $overrides);
    }

    private function runJob(CampaignLead $campaignLead, CampaignSequenceStep $step, FakeOutboundTransport $transport): void
    {
        (new SendCampaignEmailJob($campaignLead->id, $step->id))->handle($transport, new TemplateRenderer, new RoundRobinSenderSelector);
    }
}

class FakeOutboundTransport implements OutboundTransport
{
    public int $sent = 0;

    public function __construct(private bool $throw = false) {}

    public function send(SendingAccount $account, string $recipient, string $subject, string $body, string $messageId): string
    {
        $this->sent++;
        if ($this->throw) {
            throw new RuntimeException('temporary provider failure');
        }

return $messageId;
    }
}
