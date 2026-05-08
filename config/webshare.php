<?php

return [
    'api_key' => env('WEBSHARE_API_KEY'),
    'base_url' => env('WEBSHARE_API_URL', 'https://proxy.webshare.io/api/v2/'),
    'allow_custom_base_url' => (bool) env('WEBSHARE_ALLOW_CUSTOM_API_URL', false),
    'table' => env('WEBSHARE_PROXY_TABLE', 'webshare_proxies'),
    'timeout' => (int) env('WEBSHARE_TIMEOUT', 10),
    'connect_timeout' => (int) env('WEBSHARE_CONNECT_TIMEOUT', 5),
    'retry_times' => (int) env('WEBSHARE_RETRY_TIMES', 2),
    'retry_sleep_milliseconds' => (int) env('WEBSHARE_RETRY_SLEEP_MILLISECONDS', 250),
];
