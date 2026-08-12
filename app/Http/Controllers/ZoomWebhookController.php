<?php

namespace App\Http\Controllers;

use App\Jobs\SyncZoomCallSummaryJob;
use App\Models\CallRecord;
use Illuminate\Http\Request;

class ZoomWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        if ($request->input('event') === 'endpoint.url_validation') {
            return response()->json(['plainToken' => $request->input('payload.plainToken'), 'encryptedToken' => hash_hmac('sha256', $request->input('payload.plainToken'), config('zoom.webhook_secret'))]);
        }$secret = config('zoom.webhook_secret');
        abort_unless($secret && hash_equals('v0='.hash_hmac('sha256', 'v0:'.$request->header('x-zm-request-timestamp').':'.$request->getContent(), $secret), (string) $request->header('x-zm-signature')), 401);
        $event = $request->string('event')->value();
        if (str_contains($event, 'completed')) {
            $payload = $request->input('payload.object.call_logs.0', []);
            $phone = preg_replace('/\D/', '', (string) ($payload['callee_number'] ?? ''));
            $call = CallRecord::where('status', 'dialing')->whereRaw("REPLACE(REPLACE(REPLACE(phone_number,'+',''),'-',''),' ','') LIKE ?", ['%'.substr($phone, -10)])->latest()->first();
            if ($call) {
                $call->update(['provider_call_id' => $payload['call_id'] ?? $payload['id'] ?? null, 'status' => 'completed', 'duration_seconds' => $payload['duration'] ?? null, 'ended_at' => now(), 'provider_metadata' => $payload]);
                SyncZoomCallSummaryJob::dispatch($call->id);
            }
        }

return response()->json(['ok' => true]);
    }
}
