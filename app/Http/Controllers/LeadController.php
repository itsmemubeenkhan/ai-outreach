<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeadRequest;
use App\Models\Lead;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Lead::query()->with('latestCall');
        foreach (['category', 'country', 'state', 'city', 'email_status', 'phone_type', 'lead_status', 'source'] as $filter) {
            $query->when($request->filled($filter), fn ($q) => $q->where($filter, $request->string($filter)));
        }
        $query->when($request->email_availability === 'yes', fn ($q) => $q->whereNotNull('email'))
            ->when($request->email_availability === 'no', fn ($q) => $q->whereNull('email'))
            ->when($request->website_availability === 'yes', fn ($q) => $q->whereNotNull('website'))
            ->when($request->website_availability === 'no', fn ($q) => $q->whereNull('website'));
        $leads = $query->latest('id')->paginate(25)->withQueryString();
        $options = collect(['category', 'country', 'state', 'city', 'phone_type', 'source'])->mapWithKeys(fn ($f) => [$f => Lead::whereNotNull($f)->distinct()->orderBy($f)->limit(200)->pluck($f)]);

        return view('leads.index', compact('leads', 'options'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('leads.form', ['lead' => new Lead]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreLeadRequest $request)
    {
        Lead::create($this->normalized($request->validated()));

        return redirect()->route('leads.index')->with('success', 'Lead created.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Lead $lead)
    {
        return view('leads.form', compact('lead'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreLeadRequest $request, Lead $lead)
    {
        $lead->update($this->normalized($request->validated()));

        return redirect()->route('leads.index')->with('success', 'Lead updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Lead $lead)
    {
        $lead->delete();

        return back()->with('success', 'Lead deleted.');
    }

    private function normalized(array $data): array
    {
        if (! empty($data['email'])) {
            $data['email'] = strtolower(trim($data['email']));
        }
        if (! empty($data['corporate_email'])) {
            $data['corporate_email'] = strtolower(trim($data['corporate_email']));
        }
        if (! empty($data['phone'])) {
            $digits = preg_replace('/\D+/', '', $data['phone']);
            $data['phone'] = strlen($digits) === 11 && str_starts_with($digits, '1') ? '+'.$digits : (strlen($digits) === 10 ? '+1'.$digits : $data['phone']);
        }

        return $data;
    }
}
