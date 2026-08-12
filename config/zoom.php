<?php

return ['enabled' => env('ZOOM_PHONE_ENABLED', false), 'account_id' => env('ZOOM_ACCOUNT_ID'), 'client_id' => env('ZOOM_CLIENT_ID'), 'client_secret' => env('ZOOM_CLIENT_SECRET'), 'webhook_secret' => env('ZOOM_WEBHOOK_SECRET'), 'auto_next_delay' => (int) env('ZOOM_AUTO_NEXT_DELAY', 5)];
