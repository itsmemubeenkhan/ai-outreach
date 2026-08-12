<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCampaignSequenceStepRequest;
use App\Models\Campaign;
use App\Models\CampaignSequenceStep;

class CampaignSequenceStepController extends Controller
{
    public function store(StoreCampaignSequenceStepRequest $request, Campaign $campaign)
    {
        $campaign->sequenceSteps()->create($this->data($request));

        return back()->with('success', 'Sequence step added.');
    }

    public function update(StoreCampaignSequenceStepRequest $request, Campaign $campaign, CampaignSequenceStep $step)
    {
        abort_unless($step->campaign_id === $campaign->id, 404);
        $step->update($this->data($request));

        return back()->with('success', 'Sequence step updated.');
    }

    public function destroy(Campaign $campaign, CampaignSequenceStep $step)
    {
        abort_unless($step->campaign_id === $campaign->id, 404);
        $step->delete();

        return back()->with('success', 'Sequence step deleted.');
    }

    private function data(StoreCampaignSequenceStepRequest $request): array
    {
        return $request->safe()->except('enabled') + ['enabled' => $request->boolean('enabled')];
    }
}
