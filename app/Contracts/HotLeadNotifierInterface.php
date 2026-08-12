<?php

namespace App\Contracts;

use App\Models\InboundMessage;

interface HotLeadNotifierInterface
{
    public function notify(InboundMessage $message): void;
}
