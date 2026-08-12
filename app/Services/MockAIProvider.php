<?php

namespace App\Services;

use App\Contracts\AIProviderInterface;

class MockAIProvider implements AIProviderInterface
{
    public function classifyReply(string $subject, string $body): array
    {
        $text = strtolower($subject.' '.$body);
        $rules = ['unsubscribe' => ['unsubscribe', 'remove me', 'take me off your list', 'do not email me again'], 'out_of_office' => ['out of office', 'away until', 'vacation reply'], 'not_interested' => ['not interested', 'no thanks', 'no thank you'], 'callback' => ['call me', 'give me a call', 'phone me'], 'pricing' => ['price', 'pricing', 'cost', 'quote'], 'interested' => ['interested', 'sounds good', 'let’s talk', 'lets talk'], 'wrong_person' => ['wrong person'], 'later' => ['later', 'next month', 'follow up'], 'question' => ['?', 'can you', 'could you']];
        foreach ($rules as $category => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($text, $needle)) {
                    return ['classification' => $category, 'confidence' => .9];
                }
            }
        }

        return ['classification' => 'other', 'confidence' => .5];
    }

    public function recommendNextAction(string $classification, string $body): string
    {
        return match ($classification) {
            'callback' => 'Call the prospect promptly.','pricing' => 'Review context and respond personally about pricing.','interested' => 'Contact the prospect personally to qualify and close.','question' => 'Review and answer the question personally.','later' => 'Schedule a human follow-up.','out_of_office' => 'Review the return date before scheduling follow-up.','not_interested' => 'Mark reviewed; do not continue outreach.','unsubscribe' => 'No action; suppression was applied.','wrong_person' => 'Review and identify the correct contact.','other' => 'Review this reply manually.'
        };
    }
}
