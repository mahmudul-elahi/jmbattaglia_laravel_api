<?php

return [
    'media_retention_days' => (int) env('MEDIA_RETENTION_DAYS', 7),
    'registration_enabled' => (bool) env('REGISTRATION_ENABLED', false),
    'mail_disabled' => (bool) env('MAIL_DISABLED', true),
    'media_disk' => env('MEDIA_STORAGE', 'public') === 'local' ? 'public' : env('MEDIA_STORAGE', 'public'),
    'preview_store' => env('QUOTE_CACHE_STORE', 'redis'),
    'admin' => ['email' => env('ADMIN_EMAIL'), 'password' => env('ADMIN_PASSWORD'), 'name' => env('ADMIN_NAME', 'Administrator')],
];
