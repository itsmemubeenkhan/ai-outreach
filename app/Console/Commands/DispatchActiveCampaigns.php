<?php

namespace App\Console\Commands;

use App\Jobs\ProcessCampaignJob;
use App\Jobs\ProcessFollowUpsJob;
use App\Models\Campaign;
use App\Models\OutboundEmail;
use App\Models\SendingAccount;
use Illuminate\Console\Command;

class DispatchActiveCampaigns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'outreach:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile daily counters and dispatch active campaign and follow-up jobs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        SendingAccount::query()->each(function ($account) {
            $sent = OutboundEmail::where('sending_account_id', $account->id)->where('status', 'sent')->whereBetween('sent_at', [now()->startOfDay(), now()->endOfDay()])->count();
            $account->update(['sent_today' => $sent, 'last_reset_at' => now()]);
        });
        Campaign::where('status', 'active')->where(fn ($query) => $query->whereNull('start_date')->orWhere('start_date', '<=', today()))->pluck('id')->each(fn ($id) => ProcessCampaignJob::dispatch($id));
        ProcessFollowUpsJob::dispatch();
        $this->info('Campaign processing jobs dispatched.');

        return self::SUCCESS;
    }
}
