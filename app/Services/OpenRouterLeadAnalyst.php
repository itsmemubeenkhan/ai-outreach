<?php

namespace App\Services;

use App\Models\Lead;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenRouterLeadAnalyst
{
    public function analyze(Lead $lead, array $website): array
    {
        $key = config('ai.openrouter.key');
        if (! $key) throw new RuntimeException('OpenRouter is not configured.');
        $leadData = $lead->only(['business_name','contact_person','category','number_of_employees','city','state','country','website','lead_score','source']);
        $prompt = "Analyze this sales lead and its website content. Recommend only services justified by evidence. Never invent facts. Return ONLY valid JSON with keys summary (string), best_offer (string), reasons (array of strings), website_findings (array of strings), opening_pitch (string), discovery_questions (array of strings), cautions (array of strings).\n\nLEAD:\n".json_encode($leadData)."\n\nWEBSITE:\n".json_encode($website);
        $response = Http::timeout(35)->retry(2, 500)->withToken($key)->withHeaders(['HTTP-Referer' => config('app.url'), 'X-Title' => 'AI Outreach CRM'])->post(config('ai.openrouter.url'), [
            'model' => config('ai.openrouter.model'),
            'messages' => [['role' => 'system', 'content' => 'You are a concise B2B sales strategist. Ground every recommendation in supplied CRM and website evidence.'], ['role' => 'user', 'content' => $prompt]],
            'temperature' => 0.2,
            'max_tokens' => 900,
        ]);
        if (! $response->successful()) throw new RuntimeException('AI provider returned HTTP '.$response->status().'.');
        $content = $response->json('choices.0.message.content');
        $content = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim((string) $content));
        $result = json_decode($content, true);
        if (! is_array($result)) throw new RuntimeException('AI provider returned an invalid response.');

        return $result;
    }
}
