<?php

namespace App\Services;

use App\Contracts\HotLeadNotifierInterface;
use App\Models\HotLeadNotification;
use App\Models\InboundMessage;

class DatabaseHotLeadNotifier implements HotLeadNotifierInterface
{
    public function notify(InboundMessage $message): void
    {
        $userId = $message->sendingAccount->user_id;
        HotLeadNotification::firstOrCreate(['user_id' => $userId, 'inbound_message_id' => $message->id], ['title' => 'New hot lead: '.($message->lead?->business_name ?: $message->from_email), 'message' => $message->classification.': '.$message->recommended_next_action]);
    }
}
