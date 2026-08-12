<?php

namespace App\Contracts;

use App\Models\SendingAccount;

interface OutboundTransport
{
    public function send(SendingAccount $account, string $recipient, string $subject, string $body, string $messageId): string;
}
