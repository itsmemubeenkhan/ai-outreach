<?php

namespace App\Http\Controllers;

use App\Models\CallRecord;
use App\Models\DialerSession;
use App\Models\Lead;
use App\Services\DialerSessionService;
use App\Services\LeadSalesRecommendationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PowerDialerController extends Controller
{
    public function __construct(private readonly DialerSessionService $dialerSessions, private readonly LeadSalesRecommendationService $salesRecommendations) {}

    public function index()
    {
        $session = DialerSession::with('currentLead')->where('user_id', auth()->id())->whereIn('status', ['active', 'paused'])->latest()->first();
        $lead = $session?->currentLead;
        $websiteUrl = $this->websiteUrl($lead?->website);

        return view('dialer.index', ['categories' => $this->primaryCategories(), 'session' => $session, 'recentCalls' => CallRecord::with('lead')->where('user_id', auth()->id())->latest()->paginate(20), 'recommendation' => $lead ? $this->salesRecommendations->for($lead) : null, 'websiteUrl' => $websiteUrl]);
    }

    public function start(Request $request)
    {
        $data = $request->validate(['category' => 'nullable|string|max:120']);
        DialerSession::where('user_id', $request->user()->id)->whereIn('status', ['active', 'paused'])->update(['status' => 'ended', 'ended_at' => now()]);
        $session = DialerSession::create(['user_id' => $request->user()->id, 'category' => $data['category'] ?: null, 'filters' => ['category' => $data['category'] ?: null], 'status' => 'active', 'auto_next_delay' => 30, 'started_at' => now()]);
        $this->dialerSessions->advance($session);

        return redirect()->route('dialer.index');
    }

    public function state(DialerSession $dialerSession)
    {
        $this->owner($dialerSession);
        $dialerSession->load('currentLead');

        $latestCall = $dialerSession->callRecords()->latest()->first();

        return response()->json(['status' => $dialerSession->status, 'lead' => $dialerSession->currentLead, 'calls_completed' => $dialerSession->calls_completed, 'delay' => $dialerSession->auto_next_delay, 'latest_call' => $latestCall?->only(['id', 'status', 'ended_at'])]);
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
            $shouldAdvance = $callRecord->session?->current_lead_id === $callRecord->lead_id;
            $callRecord->update(['status' => 'completed', 'disposition' => $data['disposition'], 'notes' => $data['notes'] ?? null, 'ended_at' => $callRecord->ended_at ?? now()]);
            $callRecord->lead->activities()->create(['type' => 'call_completed', 'description' => 'Call completed: '.str($data['disposition'])->replace('_', ' ')->title(), 'metadata' => ['call_record_id' => $callRecord->id]]);
            if (in_array($data['disposition'], ['callback', 'interested'], true)) {
                $callRecord->lead->update(['lead_status' => 'interested']);
            }$session = $callRecord->session;
            if ($session && $shouldAdvance) {
                $session->increment('calls_completed');
                $this->dialerSessions->advance($session);
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
            $this->dialerSessions->advance($dialerSession);
        }

        return back();
    }

    private function owner(DialerSession $session): void
    {
        abort_unless($session->user_id === auth()->id(), 403);
    }

    private function websiteUrl(?string $website): ?string
    {
        if (! $website) return null;
        $url = preg_match('#^https?://#i', $website) ? $website : 'https://'.$website;

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    private function primaryCategories()
    {
        $query = Lead::whereNotNull('phone')->whereNotNull('category')->where('category', '!=', '');
        if (DB::connection()->getDriverName() === 'mysql') {
            return $query->selectRaw("TRIM(SUBSTRING_INDEX(category, ',', 1)) AS primary_category")
                ->distinct()->orderBy('primary_category')->pluck('primary_category')->filter()->values();
        }

        return $query->distinct()->pluck('category')->map(fn ($category) => trim(explode(',', $category, 2)[0]))->filter()->unique()->sort()->values();
    }
}
