<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\OutboundEmail;
use App\Models\SendingAccount;

class RoundRobinSenderSelector
{
    public function select(Campaign $campaign): ?SendingAccount
    {
        $start = now()->startOfDay();
        $end = now()->endOfDay();
        $accounts = $campaign->sendingAccounts()->where('status', 'active')->orderBy('sending_accounts.id')->lockForUpdate()->get()->filter(function ($account) use ($start, $end) {
            $used = OutboundEmail::where('sending_account_id', $account->id)->whereIn('status', ['processing', 'sent'])->whereBetween('created_at', [$start, $end])->count();

            return $used < $account->daily_limit;
        })->values();
        if ($accounts->isEmpty()) {
            return null;
        }
        $lastId = OutboundEmail::where('campaign_id', $campaign->id)->whereNotNull('sending_account_id')->latest('id')->value('sending_account_id');
        if (! $lastId) {
            return $accounts->first();
        } $index = $accounts->search(fn ($account) => $account->id === $lastId);

        return $index === false ? $accounts->first() : $accounts->get(($index + 1) % $accounts->count());
    }
}
