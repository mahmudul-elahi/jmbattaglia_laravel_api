<?php

return [
    'mode' => env('BOOKING_MODE', 'local'), 'writes_enabled' => (bool) env('ISN_WRITES_ENABLED', false),
    'base_url' => env('ISN_BASE_URL', ''), 'access_key' => env('ISN_ACCESS_KEY'), 'secret_key' => env('ISN_SECRET_KEY'),
    'office_id' => env('ISN_OFFICE_ID'), 'timezone' => env('ISN_TIMEZONE', 'America/New_York'),
    'timeout' => (int) env('ISN_TIMEOUT_MS', 10000) / 1000,
    'quote_amount_field' => env('ISN_QUOTE_AMOUNT_FIELD', 'amount'),
    'order_types' => ['RESALE_HOME' => env('ISN_RESALE_ORDER_TYPE_ID'), 'NEW_CONSTRUCTION' => env('ISN_NEW_CONSTRUCTION_ORDER_TYPE_ID')],
];
