<?php

return [
    'openrouter' => [
        'key' => env('OPENROUTER_API_KEY'),
        'model' => env('OPENROUTER_MODEL', 'openrouter/auto'),
        'url' => 'https://openrouter.ai/api/v1/chat/completions',
    ],
];
