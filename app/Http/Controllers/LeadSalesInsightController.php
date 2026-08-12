<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Services\OpenRouterLeadAnalyst;
use App\Services\SafeWebsiteReader;
use Illuminate\Support\Facades\Cache;
use Throwable;

class LeadSalesInsightController extends Controller
{
    public function __invoke(Lead $lead, SafeWebsiteReader $reader, OpenRouterLeadAnalyst $analyst)
    {
        try {
            $result = Cache::remember('lead-sales-insight:v2:'.$lead->id.':'.sha1((string) $lead->website), now()->addDay(), function () use ($lead, $reader, $analyst) {
                $website = $reader->read($lead->website);
                return ['website' => $website, 'analysis' => $analyst->analyze($lead, $website), 'generated_at' => now()->toIso8601String()];
            });
            return response()->json($result);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
