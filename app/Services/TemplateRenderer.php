<?php

namespace App\Services;

use App\Models\Lead;
use InvalidArgumentException;

class TemplateRenderer
{
    private const VARIABLES = ['first_name', 'business_name', 'city', 'state', 'category', 'website'];

    public function render(string $template, Lead $lead): string
    {
        $values = collect(self::VARIABLES)->mapWithKeys(fn ($key) => ['{{'.$key.'}}' => (string) ($lead->{$key} ?? '')])->all();
        $rendered = strtr($template, $values);
        if (preg_match('/{{\s*[^}]+\s*}}/', $rendered, $match)) {
            throw new InvalidArgumentException("Unknown template variable: {$match[0]}");
        }

        return $rendered;
    }
}
