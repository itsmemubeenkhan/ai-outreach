<?php

namespace App\Http\Controllers;

use App\Models\InboundMessage;
use App\Models\Lead;
use App\Models\OutboundEmail;
use App\Models\Task;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $counts = [
            'total' => Lead::count(), 'new' => Lead::where('lead_status', 'new')->count(),
            'queued' => OutboundEmail::where('status', 'queued')->count(), 'contacted' => OutboundEmail::where('status', 'sent')->count(),
            'opened' => Lead::where('lead_status', 'opened')->count(), 'clicked' => Lead::where('lead_status', 'clicked')->count(),
            'replied' => Lead::where('lead_status', 'replied')->count(), 'positive' => Lead::where('lead_status', 'interested')->count(),
            'hot' => Lead::where('lead_score', '>=', 70)->count(), 'due' => Lead::whereNotNull('next_follow_up_at')->where('next_follow_up_at', '<=', now())->count(),
            'failed' => OutboundEmail::where('status', 'failed')->count(), 'bounced' => Lead::where('email_status', 'bounced')->count(),
        ];

        $attention = InboundMessage::with('lead')->where('requires_human_action', true)->whereNull('reviewed_at')->latest('received_at')->limit(5)->get();
        $replyKpis = ['Replies Today' => InboundMessage::whereDate('received_at', today())->count(), 'Positive Replies' => InboundMessage::whereIn('classification', ['interested', 'pricing', 'callback'])->count(), 'Hot Leads' => InboundMessage::whereIn('classification', ['interested', 'pricing', 'callback', 'question'])->where('requires_human_action', true)->count(), 'Unread Replies' => InboundMessage::whereNull('reviewed_at')->count(), 'Calls Required' => InboundMessage::where('classification', 'callback')->where('requires_human_action', true)->count(), 'Tasks Due' => Task::where('status', 'open')->whereDate('due_at', today())->count(), 'Overdue Tasks' => Task::where('status', 'open')->where('due_at', '<', now())->count()];

        return view('dashboard', compact('counts', 'attention', 'replyKpis'));
    }
}
