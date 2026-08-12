<?php

namespace App\Jobs;

use App\Models\CallRecord;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncZoomCallSummaryJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $callRecordId)
    {
        $call = CallRecord::find($this->callRecordId);
        if (! $call) {
            return;
        }if (! config('zoom.enabled')) {
            $call->update(['summary_status' => 'unavailable']);

            return;
        }$call->update(['summary_status' => 'awaiting_provider']);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //
    }
}
