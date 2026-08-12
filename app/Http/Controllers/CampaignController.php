<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCampaignRequest;
use App\Jobs\MaterializeCampaignAudience;
use App\Models\Brand;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\SendingAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CampaignController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('campaigns.index', ['campaigns' => Campaign::with('brand')->withCount('sequenceSteps')->latest()->paginate(20)]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('campaigns.form', $this->formData(new Campaign));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCampaignRequest $request)
    {
        $data = $request->validated();
        $accountIds = $data['sending_account_ids'] ?? [];
        unset($data['sending_account_ids']);
        $audience = array_filter($data['audience'] ?? []);
        unset($data['audience']);
        $data['audience_filters'] = $audience;
        $data['audience_status'] = 'queued';
        if ($data['status'] === 'active') {
            $data['status'] = 'draft';
        }
        $campaign = Campaign::create($data);
        $campaign->sendingAccounts()->sync(SendingAccount::where('user_id', $request->user()->id)->whereIn('id', $accountIds)->pluck('id'));
        MaterializeCampaignAudience::dispatch($campaign->id);

        return redirect()->route('campaigns.show', $campaign)->with('success', 'Campaign created and audience queued. Add the sequence before activation.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Campaign $campaign)
    {
        $campaign->load(['brand', 'sequenceSteps', 'sendingAccounts'])->loadCount([
            'outboundEmails as queued_count' => fn ($query) => $query->where('status', 'queued'),
            'outboundEmails as sent_count' => fn ($query) => $query->where('status', 'sent'),
            'outboundEmails as failed_count' => fn ($query) => $query->where('status', 'failed'),
            'outboundEmails as skipped_count' => fn ($query) => $query->where('status', 'skipped'),
        ]);

        return view('campaigns.show', compact('campaign'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Campaign $campaign)
    {
        return view('campaigns.form', $this->formData($campaign));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreCampaignRequest $request, Campaign $campaign)
    {
        $data = $request->validated();
        $accountIds = $data['sending_account_ids'] ?? [];
        unset($data['sending_account_ids']);
        $audience = array_filter($data['audience'] ?? []);
        unset($data['audience']);
        if ($data['status'] === 'active' && (! $campaign->sequenceSteps()->where('enabled', true)->exists() || $campaign->audience_status !== 'ready' || empty($accountIds))) {
            throw ValidationException::withMessages(['status' => 'An active campaign needs a ready audience, an enabled sequence step, and at least one sending account.']);
        }
        $filtersChanged = $audience !== ($campaign->audience_filters ?? []);
        $data['audience_filters'] = $audience;
        if ($filtersChanged) {
            $data['audience_status'] = 'queued';
        }
        $campaign->update($data);
        $campaign->sendingAccounts()->sync(SendingAccount::where('user_id', $request->user()->id)->whereIn('id', $accountIds)->pluck('id'));
        if ($filtersChanged) {
            MaterializeCampaignAudience::dispatch($campaign->id);
        }

        return redirect()->route('campaigns.show', $campaign)->with('success', 'Campaign updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Campaign $campaign)
    {
        abort_if($campaign->status === 'active', 422, 'Pause the campaign before deleting it.');
        $campaign->delete();

        return redirect()->route('campaigns.index')->with('success', 'Campaign deleted.');
    }

    public function rebuildAudience(Campaign $campaign)
    {
        $campaign->update(['audience_status' => 'queued']);
        MaterializeCampaignAudience::dispatch($campaign->id);

        return back()->with('success', 'Audience rebuild queued.');
    }

    public function audiencePreview(Request $request)
    {
        $filters = array_filter($request->input('audience', []));
        $query = Lead::query();
        foreach (['category', 'country', 'state', 'city', 'email_status', 'phone_type', 'lead_status', 'source'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (($filters['email_availability'] ?? null) === 'yes') {
            $query->whereNotNull('email');
        } if (($filters['email_availability'] ?? null) === 'no') {
            $query->whereNull('email');
        }
        if (($filters['website_availability'] ?? null) === 'yes') {
            $query->whereNotNull('website');
        } if (($filters['website_availability'] ?? null) === 'no') {
            $query->whereNull('website');
        }

        return response()->json(['count' => $query->count()]);
    }

    private function formData(Campaign $campaign): array
    {
        $options = collect(['category', 'country', 'state', 'city', 'phone_type', 'source'])->mapWithKeys(fn ($field) => [$field => Lead::whereNotNull($field)->distinct()->orderBy($field)->limit(200)->pluck($field)]);

        return ['campaign' => $campaign->loadMissing('sendingAccounts'), 'brands' => Brand::where('is_active', true)->orderBy('name')->get(), 'sendingAccounts' => SendingAccount::where('user_id', auth()->id())->orderBy('name')->get(), 'options' => $options];
    }
}
