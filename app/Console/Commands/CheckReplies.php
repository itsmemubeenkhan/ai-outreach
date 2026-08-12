<?php

namespace App\Console\Commands;

use App\Jobs\CheckSendingAccountInboxJob;
use App\Models\SendingAccount;
use Illuminate\Console\Command;

class CheckReplies extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'outreach:check-replies';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch one reply-check job per eligible mailbox';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $accounts = SendingAccount::where('status', 'active')->whereNotNull('imap_host')->whereNotNull('imap_password')->pluck('id');
        $accounts->each(fn ($id) => CheckSendingAccountInboxJob::dispatch($id));
        $this->info("Dispatched {$accounts->count()} mailbox checks.");

        return self::SUCCESS;
    }
}
