<?php

return [
    // Database configuration
    'db' => [
        'host' => 'localhost',
        'name' => 'image_service',
        'user' => 'your_username',
        'pass' => 'your_password',
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    ],

    // Upload settings
    'upload' => [
        'max_file_size' => 10 * 1024 * 1024, // 10MB
        'allowed_types' => ['image/jpeg', 'image/png', 'image/gif'],
        'max_width' => 5000,
        'max_height' => 5000,
        'min_width' => 1,
        'min_height' => 1,
    ],

    // Directory paths
    'paths' => [
        'uploads' => __DIR__ . '/../../public/uploads',
        'hidden' => __DIR__ . '/../../hidden_images',
        'logs' => __DIR__ . '/../../logs',
        'cache' => __DIR__ . '/../../cache',
    ],

    // Logging configuration
    'logging' => [
        'enabled' => true,
        'level' => 'debug', // debug, info, warning, error
        'max_files' => 30,
        'max_size' => 10 * 1024 * 1024, // 10MB
    ],

    // Security settings
    'security' => [
        'allowed_hosts' => ['*'], // Add specific hosts for production
        'max_attempts' => 5, // Max upload attempts per minute
        'secure_file_names' => true,
    ],
];
