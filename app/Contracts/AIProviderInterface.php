<?php

namespace App\Contracts;

interface AIProviderInterface
{
    public function classifyReply(string $subject, string $body): array;

    public function recommendNextAction(string $classification, string $body): string;
}
