<?php

namespace Tests\Feature;

use App\Jobs\ClassifyInboundMessageJob;
use App\Models\Brand;
use App\Models\Campaign;
use App\Models\CampaignLead;
use App\Models\CampaignSequenceStep;
use App\Models\InboundMessage;
use App\Models\Lead;
use App\Models\OutboundEmail;
use App\Models\SendingAccount;
use App\Models\User;
use App\Services\DatabaseHotLeadNotifier;
use App\Services\LeadScoringService;
use App\Services\MockAIProvider;
use App\Services\ReplyIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReplyWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_message_id_match_is_idempotent_and_stops_campaign(): void
    {
        Queue::fake();
        [$account,$outbound,$lead,$cl] = $this->scenario();
        $data = $this->reply(['in_reply_to' => '<'.$outbound->message_id.'>']);
        $service = new ReplyIngestionService;
        $first = $service->ingest($account, $data);
        $second = $service->ingest($account, $data);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, InboundMessage::count());
        $this->assertSame('stopped', $cl->refresh()->status);
        $this->assertSame('replied', $lead->refresh()->lead_status);
    }

    public function test_uncertain_reply_remains_unmatched_and_html_is_text_only(): void
    {
        Queue::fake();
        [$account] = $this->scenario();
        $m = (new ReplyIngestionService)->ingest($account, $this->reply(['internet_message_id' => 'unknown', 'from_email' => 'unknown@example.com', 'body_text' => '', 'body_html' => '<script>alert(1)</script><b>Hello</b>']));
        $this->assertNull($m->lead_id);
        $this->assertSame('alert(1)Hello', $m->body_text);
        $this->actingAs($account->owner)->get(route('inbox.show', $m))->assertOk()->assertDontSee('<script>', false);
    }

    public function test_unsubscribe_reply_suppresses_address(): void
    {
        Queue::fake();
        [$account,$outbound,$lead] = $this->scenario();
        (new ReplyIngestionService)->ingest($account, $this->reply(['in_reply_to' => $outbound->message_id, 'body_text' => 'Remove me']));
        $this->assertDatabaseHas('suppressions', ['email' => $lead->email, 'reason' => 'unsubscribe']);
        $this->assertSame('unsubscribed', $lead->refresh()->lead_status);
    }

    public function test_mock_classification_hot_leads_and_scoring_are_idempotent(): void
    {
        foreach (['interested' => true, 'pricing' => true, 'callback' => true, 'out_of_office' => false, 'not_interested' => false] as $word => $hot) {
            Queue::fake();
            [$account,$outbound,$lead] = $this->scenario();
            $m = (new ReplyIngestionService)->ingest($account, $this->reply(['internet_message_id' => 'id-'.$word, 'in_reply_to' => $outbound->message_id, 'body_text' => $word === 'callback' ? 'Please call me' : str_replace('_', ' ', $word)]));
            $job = new ClassifyInboundMessageJob($m->id);
            $provider = new MockAIProvider;
            $notifier = new DatabaseHotLeadNotifier;
            $job->handle($provider, new LeadScoringService, $notifier);
            $score = $lead->refresh()->lead_score;
            $job->handle($provider, new LeadScoringService, $notifier);
            $this->assertSame($score, $lead->refresh()->lead_score);
            $this->assertSame($hot, $m->refresh()->requires_human_action && in_array($m->classification, ['interested', 'pricing', 'callback', 'question']));
        }
    }

    public function test_task_can_be_created_from_hot_lead(): void
    {
        Queue::fake();
        [$account,$outbound] = $this->scenario();
        $m = (new ReplyIngestionService)->ingest($account, $this->reply(['in_reply_to' => $outbound->message_id]));
        $this->actingAs($account->owner)->post(route('inbox.task', $m), ['title' => 'Call prospect', 'priority' => 'urgent'])->assertRedirect();
        $this->assertDatabaseHas('tasks', ['inbound_message_id' => $m->id, 'title' => 'Call prospect']);
    }

    private function scenario(): array
    {
        static $i = 0;
        $i++;
        $user = User::factory()->create();
        $brand = Brand::create(['name' => 'B'.$i, 'slug' => 'b'.$i]);
        $campaign = Campaign::create(['brand_id' => $brand->id, 'name' => 'C', 'status' => 'active', 'daily_limit' => 10, 'sender_strategy' => 'round_robin']);
        $lead = Lead::create(['email' => 'lead'.$i.'@example.com', 'lead_status' => 'contacted', 'email_status' => 'valid']);
        $cl = CampaignLead::create(['campaign_id' => $campaign->id, 'lead_id' => $lead->id, 'status' => 'pending']);
        $step = CampaignSequenceStep::create(['campaign_id' => $campaign->id, 'subject' => 'Hi', 'body' => 'Body', 'delay_days' => 0, 'position' => 1]);
        $account = SendingAccount::create(['user_id' => $user->id, 'name' => 'A', 'sender_name' => 'S', 'email' => 'sender'.$i.'@example.com', 'smtp_host' => 'x', 'smtp_port' => 1, 'smtp_username' => 'x', 'smtp_password' => 'x', 'smtp_encryption' => 'none', 'imap_host' => 'x', 'imap_port' => 993, 'imap_username' => 'x', 'imap_password' => 'x', 'imap_encryption' => 'ssl', 'daily_limit' => 10, 'status' => 'active']);
        $out = OutboundEmail::create(['campaign_id' => $campaign->id, 'campaign_lead_id' => $cl->id, 'lead_id' => $lead->id, 'campaign_sequence_step_id' => $step->id, 'sending_account_id' => $account->id, 'message_uuid' => (string) Str::uuid(), 'message_id' => 'msg'.$i.'@local', 'subject' => 'Hi', 'recipient_email' => $lead->email, 'status' => 'sent', 'sent_at' => now()]);

        return [$account, $out, $lead, $cl];
    }

    private function reply(array $over = []): array
    {
        return array_merge(['internet_message_id' => 'reply@local', 'from_email' => 'lead@example.com', 'to_email' => 'sender@example.com', 'subject' => 'Re: Hi', 'body_text' => 'I am interested', 'received_at' => now()], $over);
    }
}
