<?php

namespace App\Http\Controllers;

use App\Models\CampaignLead;
use App\Models\OutboundEmail;
use App\Models\Suppression;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UnsubscribeController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        abort_unless($request->hasValidSignature(), 403);
        $outbound = OutboundEmail::where('message_uuid', $request->string('message'))->firstOrFail();
        DB::transaction(function () use ($outbound) {
            $email = Suppression::normalize($outbound->recipient_email);
            Suppression::updateOrCreate(['email' => $email], ['reason' => 'unsubscribe', 'source' => 'email_link']);
            $outbound->lead()->update(['lead_status' => 'unsubscribed']);
            CampaignLead::where('lead_id', $outbound->lead_id)->whereNull('stopped_at')->update(['status' => 'stopped', 'stopped_at' => now(), 'stop_reason' => 'unsubscribed']);
            OutboundEmail::where('lead_id', $outbound->lead_id)->where('status', 'queued')->update(['status' => 'cancelled', 'failure_reason' => 'Unsubscribed']);
        });

        return view('unsubscribe-confirmed');
    }
}
