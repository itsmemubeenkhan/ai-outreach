<?php

namespace App\Jobs;

use App\Contracts\OutboundTransport;
use App\Models\CampaignLead;
use App\Models\CampaignSequenceStep;
use App\Models\OutboundEmail;
use App\Models\Suppression;
use App\Services\RoundRobinSenderSelector;
use App\Services\TemplateRenderer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

class SendCampaignEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $campaignLeadId, public int $sequenceStepId)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(OutboundTransport $transport, TemplateRenderer $renderer, RoundRobinSenderSelector $selector): void
    {
        $prepared = DB::transaction(function () use ($renderer, $selector) {
            $campaignLead = CampaignLead::lockForUpdate()->find($this->campaignLeadId);
            $step = CampaignSequenceStep::find($this->sequenceStepId);
            if (! $campaignLead || ! $step || $step->campaign_id !== $campaignLead->campaign_id) {
                return null;
            }
            $campaignLead->load(['campaign', 'lead']);
            $campaign = $campaignLead->campaign;
            $lead = $campaignLead->lead;
            $outbound = OutboundEmail::firstOrCreate(['campaign_lead_id' => $campaignLead->id, 'campaign_sequence_step_id' => $step->id], ['campaign_id' => $campaign->id, 'lead_id' => $lead->id, 'message_uuid' => (string) Str::uuid(), 'subject' => $step->subject, 'recipient_email' => (string) $lead->email, 'status' => 'queued', 'queued_at' => now()]);
            if (in_array($outbound->status, ['processing', 'sent', 'skipped', 'cancelled'], true) || $outbound->attempt_count > 0) {
                return null;
            }
            $reason = $this->guardFailure($campaignLead, $step);
            if ($reason) {
                $outbound->update(['status' => 'skipped', 'failure_reason' => $reason]);

                return null;
            }
            $campaignUsed = OutboundEmail::where('campaign_id', $campaign->id)->whereIn('status', ['processing', 'sent'])->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])->count();
            if ($campaignUsed >= $campaign->daily_limit) {
                return null;
            }
            $account = $selector->select($campaign);
            if (! $account) {
                return null;
            }
            try {
                $subject = $renderer->render($step->subject, $lead);
                $body = $renderer->render($step->body, $lead);
            } catch (Throwable $e) {
                $outbound->update(['status' => 'skipped', 'failure_reason' => 'Template rendering failed']);

                return null;
            }
            $unsubscribe = URL::signedRoute('unsubscribe', ['message' => $outbound->message_uuid]);
            $body .= "\n\nUnsubscribe: {$unsubscribe}";
            $messageId = $outbound->message_uuid.'@ai-outreach.local';
            $outbound->update(['sending_account_id' => $account->id, 'subject' => $subject, 'recipient_email' => strtolower(trim($lead->email)), 'message_id' => $messageId, 'status' => 'processing', 'processing_at' => now(), 'attempt_count' => 1, 'failure_reason' => null]);

            return [$outbound->id, $account->id, $lead->email, $subject, $body, $messageId];
        }, 3);
        if (! $prepared) {
            return;
        } [$outboundId,$accountId,$recipient,$subject,$body,$messageId] = $prepared;
        $outbound = OutboundEmail::findOrFail($outboundId);
        $account = $outbound->sendingAccount;
        try {
            $transport->send($account, $recipient, $subject, $body, $messageId);
            DB::transaction(function () use ($outbound, $account) {
                $email = OutboundEmail::lockForUpdate()->find($outbound->id);
                if ($email->status !== 'processing') {
                    return;
                }$sentAt = now();
                $email->update(['status' => 'sent', 'sent_at' => $sentAt]);
                $account->increment('sent_today');
                $account->update(['last_sent_at' => $sentAt, 'last_error' => null]);
                $campaignLead = CampaignLead::lockForUpdate()->find($email->campaign_lead_id);
                $campaignLead->lead()->update(['lead_status' => 'contacted', 'last_contacted_at' => $sentAt]);
                $next = $campaignLead->campaign->sequenceSteps()->where('enabled', true)->where('position', '>', $email->sequenceStep->position)->orderBy('position')->first();
                $campaignLead->update(['current_step' => $email->sequenceStep->position, 'status' => $next ? 'pending' : 'completed', 'next_send_at' => $next ? $sentAt->copy()->addDays($next->delay_days) : null]);
            });
        } catch (Throwable $e) {
            $message = $this->safeFailure($e);
            $outbound->update(['status' => 'failed', 'failed_at' => now(), 'failure_reason' => $message]);
            if (str_contains(strtolower($e->getMessage()), 'auth')) {
                $account->update(['status' => 'error', 'last_error' => 'SMTP authentication failed.']);
            }
        }
    }

    private function guardFailure(CampaignLead $campaignLead, CampaignSequenceStep $step): ?string
    {
        $lead = $campaignLead->lead;
        $campaign = $campaignLead->campaign;
        if ($campaign->status !== 'active') {
            return 'Campaign is not active';
        } if ($campaignLead->status !== 'pending' || $campaignLead->stopped_at) {
            return 'Campaign lead is stopped';
        }
        if (in_array($lead->lead_status, ['replied', 'interested', 'not_interested', 'unsubscribed'], true)) {
            return 'Lead replied or cannot be contacted';
        } if (! $lead->email) {
            return 'Lead has no email';
        }
        if (in_array($lead->email_status, ['invalid', 'bounced'], true)) {
            return 'Email is invalid or bounced';
        } if (Suppression::where('email', Suppression::normalize($lead->email))->exists()) {
            return 'Email is suppressed';
        }
        if (! $step->enabled) {
            return 'Sequence step is disabled';
        } if ($campaignLead->current_step > 0 && (! $campaignLead->next_send_at || $campaignLead->next_send_at->isFuture())) {
            return 'Follow-up is not due';
        }
        if ($campaignLead->current_step > 0 && ! OutboundEmail::where('campaign_lead_id', $campaignLead->id)->where('status', 'sent')->whereHas('sequenceStep', fn ($q) => $q->where('position', $campaignLead->current_step))->exists()) {
            return 'Previous step was not sent';
        }

        return null;
    }

    private function safeFailure(Throwable $e): string
    {
        $message = strtolower($e->getMessage());
        if (str_contains($message, 'auth')) {
            return 'Permanent: SMTP authentication failed';
        } if (str_contains($message, 'recipient') || str_contains($message,'mailbox')) {
            return 'Permanent: recipient rejected';
        } if (str_contains($message,'timeout')) {
            return 'Temporary: SMTP timeout (delivery not retried automatically to prevent duplicates)';
        }

return 'SMTP delivery failed (not retried automatically to prevent duplicates)';
    }
}
