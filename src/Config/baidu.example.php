<?php

return [
    // Baidu AI API credentials
    'client_id' => 'your_api_key',
    'client_secret' => 'your_secret_key',

    // API endpoints
    'token_url' => 'https://aip.baidubce.com/oauth/2.0/token',
    'censor_url' => 'https://aip.baidubce.com/rest/2.0/solution/v1/img_censor/v2/user_defined',

    // Cache settings
    'token_cache_file' => __DIR__ . '/../../cache/baidu_token.json',
    'token_cache_duration' => 2592000, // 30 days in seconds

    // Request settings
    'timeout' => 30,
    'connect_timeout' => 10,
    'retry_attempts' => 3,
    'retry_delay' => 1, // seconds

    // Content moderation settings
    'moderation' => [
        'enabled' => true,
        'min_confidence' => 0.8,
        'categories' => [
            'porn' => true,      // Adult content
            'terrorism' => true, // Violent/terrorist content
            'politics' => true,  // Political content
            'ad' => false,       // Advertisement content
        ],
    ],
];
