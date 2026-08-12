<?php

namespace App\Jobs;

use App\Contracts\AIProviderInterface;
use App\Contracts\HotLeadNotifierInterface;
use App\Models\InboundMessage;
use App\Services\LeadScoringService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ClassifyInboundMessageJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $inboundMessageId)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(AIProviderInterface $provider, LeadScoringService $scoring, HotLeadNotifierInterface $notifier): void
    {
        $message = InboundMessage::with(['lead', 'sendingAccount'])->find($this->inboundMessageId);
        if (! $message || $message->ai_processed_at) {
            return;
        }
        $result = $message->classification === 'unsubscribe' ? ['classification' => 'unsubscribe', 'confidence' => 1] : $provider->classifyReply($message->subject ?? '', $message->body_text);
        $classification = $result['classification'];
        $human = in_array($classification, ['interested', 'pricing', 'callback', 'question', 'later', 'wrong_person', 'other'], true);
        $message->update(['classification' => $classification, 'classification_confidence' => $result['confidence'] ?? null, 'recommended_next_action' => $provider->recommendNextAction($classification, $message->body_text), 'requires_human_action' => $human, 'ai_processed_at' => now()]);
        if ($message->lead) {
            $scoring->applyReply($message);
            $message->lead->activities()->create(['inbound_message_id' => $message->id, 'type' => 'classification_completed', 'description' => 'Reply classified as '.$classification]);
        }
        if (in_array($classification, ['interested', 'pricing', 'callback', 'question'], true)) {
            $notifier->notify($message);
        }
    }
}
