<?php

namespace App\Services;

use App\Jobs\ClassifyInboundMessageJob;
use App\Models\CampaignLead;
use App\Models\InboundMessage;
use App\Models\LeadActivity;
use App\Models\OutboundEmail;
use App\Models\SendingAccount;
use App\Models\Suppression;
use Illuminate\Support\Facades\DB;

class ReplyIngestionService
{
    public function ingest(SendingAccount $account, array $data): InboundMessage
    {
        return DB::transaction(function () use ($account, $data) {
            $id = $this->normalizeId($data['internet_message_id'] ?? '');
            $existing = InboundMessage::where('sending_account_id', $account->id)->where('internet_message_id', $id)->first();
            if ($existing) {
                return $existing;
            }$outbound = $this->match($account, $data);
            $lead = $outbound?->lead;
            $campaignLead = $outbound?->campaignLead;
            $body = trim(strip_tags((string) ($data['body_text'] ?: $data['body_html'] ?? '')));
            $message = InboundMessage::create(['sending_account_id' => $account->id, 'lead_id' => $lead?->id, 'campaign_id' => $outbound?->campaign_id, 'campaign_lead_id' => $campaignLead?->id, 'outbound_email_id' => $outbound?->id, 'internet_message_id' => $id, 'in_reply_to' => $this->normalizeId($data['in_reply_to'] ?? '') ?: null, 'references_header' => $data['references_header'] ?? null, 'from_email' => strtolower(trim($data['from_email'])), 'from_name' => $data['from_name'] ?? null, 'to_email' => strtolower(trim($data['to_email'])), 'subject' => $data['subject'] ?? null, 'body_text' => $body, 'body_html' => $data['body_html'] ?? null, 'received_at' => $data['received_at'] ?? now(), 'processed_at' => now(), 'raw_metadata' => $data['raw_metadata'] ?? null]);
            if ($campaignLead) {
                $campaignLead->update(['status' => 'stopped', 'replied_at' => $message->received_at, 'stopped_at' => now(), 'stop_reason' => 'reply_received', 'next_send_at' => null]);
                $lead->update(['lead_status' => 'replied']);
                OutboundEmail::where('campaign_lead_id', $campaignLead->id)->where('status', 'queued')->update(['status' => 'cancelled', 'failure_reason' => 'Reply received']);
                LeadActivity::create(['lead_id' => $lead->id, 'inbound_message_id' => $message->id, 'type' => 'reply_received', 'description' => 'Reply received; automation stopped']);
            }
            if ($this->isUnsubscribe($body) && $lead) {
                Suppression::updateOrCreate(['email' => Suppression::normalize($lead->email)], ['reason' => 'unsubscribe', 'source' => 'reply']);
                $lead->update(['lead_status' => 'unsubscribed']);
                CampaignLead::where('lead_id', $lead->id)->whereNull('stopped_at')->update(['status' => 'stopped', 'stopped_at' => now(), 'stop_reason' => 'unsubscribe']);
                $message->update(['classification' => 'unsubscribe', 'classification_confidence' => 1, 'requires_human_action' => false]);
            }
            ClassifyInboundMessageJob::dispatch($message->id);

            return $message;
        });
    }

    private function match(SendingAccount $account, array $data): ?OutboundEmail
    {
        $ids = array_filter(array_merge([$this->normalizeId($data['in_reply_to'] ?? '')], preg_split('/\s+/', str_replace(['<', '>'], '', $data['references_header'] ?? '')) ?: []));
        foreach ($ids as $id) {
            $match = OutboundEmail::where('sending_account_id', $account->id)->where('message_id', $id)->first();
            if ($match) {
                return $match;
            }
        }$from = strtolower(trim($data['from_email'] ?? ''));
        $matches = OutboundEmail::where('sending_account_id', $account->id)->where('recipient_email', $from)->where('status', 'sent')->latest('sent_at')->limit(2)->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function normalizeId(string $id): string
    {
        return trim($id, " <>\t\r\n");
    }

    private function isUnsubscribe(string $body): bool
    {
        return (bool) preg_match('/^(\s*)(stop|unsubscribe|remove me|take me off (your|the) list|do not email me again)[.!\s]*$/i',trim($body));
    }
}
