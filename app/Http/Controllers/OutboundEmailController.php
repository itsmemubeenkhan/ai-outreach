<?php

namespace App\Http\Controllers;

use App\Models\OutboundEmail;
use Illuminate\Http\Request;

class OutboundEmailController extends Controller
{
    public function index(Request $request)
    {
        $query = OutboundEmail::with(['lead', 'campaign', 'sendingAccount', 'sequenceStep']);
        $query->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('campaign_id'), fn ($q) => $q->where('campaign_id', $request->integer('campaign_id')))
            ->when($request->filled('sending_account_id'), fn ($q) => $q->where('sending_account_id', $request->integer('sending_account_id')));

        return view('outbound-emails.index', ['emails' => $query->latest()->paginate(30)->withQueryString()]);
    }
}
