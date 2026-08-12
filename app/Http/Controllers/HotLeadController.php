<?php

namespace App\Http\Controllers;

use App\Models\InboundMessage;

class HotLeadController extends Controller
{
    public function index()
    {
        return view('hot-leads.index', ['messages' => InboundMessage::with(['lead', 'campaign'])->whereIn('classification', ['interested', 'pricing', 'callback', 'question'])->where('requires_human_action', true)->latest('received_at')->paginate(25)]);
    }
}
