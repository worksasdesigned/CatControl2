<?php
// CatControl Database Configuration
// Basic configuration for testing

return [
    'host' => 'localhost',
    'database' => 'catcontrol',
    'username' => 'phpuser',
    'password' => 'changeme123',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ],
    'smtp' => [
        'host' => '',
        'port' => 587,
        'username' => '',
        'password' => '',
        'encryption' => 'tls',
        'from_email' => 'admin@localhost',
        'from_name' => 'CatControl'
    ]
];