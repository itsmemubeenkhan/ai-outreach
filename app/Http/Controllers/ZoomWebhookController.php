<?php

namespace App\Http\Controllers;

use App\Jobs\SyncZoomCallSummaryJob;
use App\Models\CallRecord;
use App\Services\DialerSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ZoomWebhookController extends Controller
{
    public function __construct(private readonly DialerSessionService $dialerSessions) {}

    public function __invoke(Request $request)
    {
        if ($request->input('event') === 'endpoint.url_validation') {
            return response()->json(['plainToken' => $request->input('payload.plainToken'), 'encryptedToken' => hash_hmac('sha256', $request->input('payload.plainToken'), config('zoom.webhook_secret'))]);
        }$secret = config('zoom.webhook_secret');
        abort_unless($secret && hash_equals('v0='.hash_hmac('sha256', 'v0:'.$request->header('x-zm-request-timestamp').':'.$request->getContent(), $secret), (string) $request->header('x-zm-signature')), 401);
        $event = $request->string('event')->value();
        if (str_contains($event, 'completed')) {
            $payload = $request->input('payload.object.call_logs.0')
                ?? $request->input('payload.object.call_element')
                ?? $request->input('payload.object');
            $phone = preg_replace('/\D/', '', (string) ($payload['callee_number'] ?? $payload['callee_did_number'] ?? $payload['callee_phone_number'] ?? ''));
            $query = CallRecord::where('status', 'dialing');
            if (strlen($phone) >= 7) {
                $query->whereRaw("REPLACE(REPLACE(REPLACE(phone_number,'+',''),'-',''),' ','') LIKE ?", ['%'.substr($phone, -10)]);
            }
            $call = $query->latest()->first();
            if ($call) {
                DB::transaction(function () use ($call, $payload) {
                    $call->lockForUpdate()->refresh();
                    if ($call->status !== 'dialing') {
                        return;
                    }
                    $call->update(['provider_call_id' => $payload['call_id'] ?? $payload['call_element_id'] ?? $payload['id'] ?? null, 'status' => 'completed', 'duration_seconds' => $payload['duration'] ?? null, 'ended_at' => now(), 'provider_metadata' => $payload]);
                    $session = $call->session;
                    if ($session && $session->status === 'active' && $session->current_lead_id === $call->lead_id) {
                        $session->increment('calls_completed');
                        $this->dialerSessions->advance($session);
                    }
                });
                SyncZoomCallSummaryJob::dispatch($call->id);
            }
        }

return response()->json(['ok' => true]);
    }
}
