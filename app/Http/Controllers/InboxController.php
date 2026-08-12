<?php

namespace App\Http\Controllers;

use App\Models\InboundMessage;
use App\Models\LeadActivity;
use App\Models\Suppression;
use App\Models\Task;
use Illuminate\Http\Request;

class InboxController extends Controller
{
    public function index(Request $request)
    {
        $q = InboundMessage::with(['lead', 'campaign']);
        $filter = $request->string('filter')->value();
        if ($filter === 'needs_action') {
            $q->where('requires_human_action', true)->whereNull('reviewed_at');
        } elseif ($filter === 'unmatched') {
            $q->whereNull('lead_id');
        } elseif ($filter) {
            $q->where('classification', $filter);
        }

        return view('inbox.index', ['messages' => $q->latest('received_at')->paginate(25)->withQueryString()]);
    }

    public function show(InboundMessage $inboundMessage)
    {
        $inboundMessage->load(['lead.activities', 'campaign', 'sendingAccount', 'outboundEmail']);

        return view('inbox.show', ['message' => $inboundMessage]);
    }

    public function action(Request $request, InboundMessage $inboundMessage)
    {
        $data = $request->validate(['action' => 'required|in:reviewed,interested,not_interested,unsubscribe,called,won,lost', 'note' => 'nullable|string|max:2000']);
        $message = $inboundMessage;
        $lead = $message->lead;
        abort_unless($lead, 422);
        $status = match ($data['action']) {
            'interested' => 'interested','not_interested' => 'not_interested','unsubscribe' => 'unsubscribed','won' => 'closed','lost' => 'closed',default => $lead->lead_status
        };
        $lead->update(['lead_status' => $status]);
        if ($data['action'] === 'unsubscribe') {
            Suppression::updateOrCreate(['email' => Suppression::normalize($lead->email)], ['reason' => 'unsubscribe', 'source' => 'inbox']);
        }$message->update(['reviewed_at' => now(), 'requires_human_action' => false]);
        LeadActivity::create(['lead_id' => $lead->id, 'inbound_message_id' => $message->id, 'type' => $data['action'], 'description' => $data['note'] ?: str($data['action'])->replace('_', ' ')->title()]);

        return back()->with('success', 'Lead action recorded.');
    }

    public function task(Request $request, InboundMessage $inboundMessage)
    {
        abort_unless($inboundMessage->lead_id, 422);
        $data = $request->validate(['title' => 'required|string|max:255', 'notes' => 'nullable|string|max:5000', 'due_at' => 'nullable|date', 'priority' => 'required|in:low,normal,high,urgent']);
        Task::create($data + ['lead_id' => $inboundMessage->lead_id, 'inbound_message_id' => $inboundMessage->id]);
        LeadActivity::create(['lead_id' => $inboundMessage->lead_id, 'inbound_message_id' => $inboundMessage->id, 'type' => 'task_created', 'description' => 'Task created: '.$data['title']]);

        return back()->with('success', 'Task created.');
    }
}
