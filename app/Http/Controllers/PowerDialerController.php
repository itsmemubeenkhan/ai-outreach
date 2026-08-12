<?php

namespace App\Http\Controllers;

use App\Models\CallRecord;
use App\Models\DialerSession;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PowerDialerController extends Controller
{
    public function index()
    {
        return view('dialer.index', ['categories' => Lead::whereNotNull('phone')->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'), 'session' => DialerSession::with('currentLead')->where('user_id', auth()->id())->whereIn('status', ['active', 'paused'])->latest()->first(), 'recentCalls' => CallRecord::with('lead')->where('user_id', auth()->id())->latest()->paginate(20)]);
    }

    public function start(Request $request)
    {
        $data = $request->validate(['category' => 'nullable|string|max:120', 'auto_next_delay' => 'required|integer|min:3|max:30']);
        DialerSession::where('user_id', $request->user()->id)->whereIn('status', ['active', 'paused'])->update(['status' => 'ended', 'ended_at' => now()]);
        $session = DialerSession::create(['user_id' => $request->user()->id, 'category' => $data['category'] ?: null, 'filters' => ['category' => $data['category'] ?: null], 'status' => 'active', 'auto_next_delay' => $data['auto_next_delay'], 'started_at' => now()]);
        $this->advance($session);

        return redirect()->route('dialer.index');
    }

    public function state(DialerSession $dialerSession)
    {
        $this->owner($dialerSession);
        $dialerSession->load('currentLead');

        return response()->json(['status' => $dialerSession->status, 'lead' => $dialerSession->currentLead, 'calls_completed' => $dialerSession->calls_completed, 'delay' => $dialerSession->auto_next_delay]);
    }

    public function dial(DialerSession $dialerSession)
    {
        $this->owner($dialerSession);
        abort_unless($dialerSession->status === 'active' && $dialerSession->currentLead?->phone, 422);
        $call = CallRecord::create(['dialer_session_id' => $dialerSession->id, 'lead_id' => $dialerSession->current_lead_id, 'user_id' => auth()->id(), 'uuid' => (string) Str::uuid(), 'phone_number' => $dialerSession->currentLead->phone, 'status' => 'dialing', 'started_at' => now()]);

        return response()->json([
            'call_id' => $call->id,
            'dial_url' => 'zoomphonecall://'.preg_replace('/[^+0-9]/', '', $call->phone_number),
        ]);
    }

    public function disposition(Request $request, CallRecord $callRecord)
    {
        abort_unless($callRecord->user_id === auth()->id(), 403);
        $data = $request->validate(['disposition' => 'required|in:answered,no_answer,busy,callback,interested,wrong_number,not_interested', 'notes' => 'nullable|string|max:3000']);
        DB::transaction(function () use ($callRecord, $data) {
            $callRecord->update(['status' => 'completed', 'disposition' => $data['disposition'], 'notes' => $data['notes'] ?? null, 'ended_at' => $callRecord->ended_at ?? now()]);
            $callRecord->lead->activities()->create(['type' => 'call_completed', 'description' => 'Call completed: '.str($data['disposition'])->replace('_', ' ')->title(), 'metadata' => ['call_record_id' => $callRecord->id]]);
            if (in_array($data['disposition'], ['callback', 'interested'], true)) {
                $callRecord->lead->update(['lead_status' => 'interested']);
            }$session = $callRecord->session;
            if ($session) {
                $session->increment('calls_completed');
                $this->advance($session);
            }
        });

        return back()->with('success', 'Call saved; next lead is ready.');
    }

    public function control(Request $request, DialerSession $dialerSession)
    {
        $this->owner($dialerSession);
        $action = $request->validate(['action' => 'required|in:pause,resume,skip,stop'])['action'];
        if ($action === 'pause') {
            $dialerSession->update(['status' => 'paused']);
        } elseif ($action === 'resume') {
            $dialerSession->update(['status' => 'active']);
        } elseif ($action === 'stop') {
            $dialerSession->update(['status' => 'ended', 'ended_at' => now(), 'current_lead_id' => null]);
        } else {
            $this->advance($dialerSession);
        }

        return back();
    }

    private function advance(DialerSession $session): void
    {
        $query = Lead::whereNotNull('phone')->where('id', '>', $session->last_lead_id ?? 0)->whereNotIn('lead_status', ['unsubscribed', 'closed']);
        if ($session->category) {
            $query->where('category', $session->category);
        }$lead = $query->orderBy('id')->first();
        if (! $lead) {
            $session->update(['status' => 'completed', 'ended_at' => now(), 'current_lead_id' => null]);

            return;
        }$session->update(['current_lead_id' => $lead->id, 'last_lead_id' => $lead->id]);
    }

    private function owner(DialerSession $session): void
    {
        abort_unless($session->user_id === auth()->id(), 403);
    }
}
