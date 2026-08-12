<?php

return [
    'openrouter' => [
        'key' => env('OPENROUTER_API_KEY'),
        'model' => env('OPENROUTER_MODEL', 'google/gemini-2.5-flash-lite'),
        'url' => 'https://openrouter.ai/api/v1/chat/completions',
    ],
];
