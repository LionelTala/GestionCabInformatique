<?php

return [
    'paths' => [
        'api/*', 
        'sanctum/csrf-cookie', 
        'v1/auth/*',          // ✅ Ajoute explicitement vos routes v1 d'authentification
        'broadcasting/auth'
    ],
    'allowed_methods' => ['*'],
    'allowed_origins' => [env('FRONTEND_URL')],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true, // 🔥 Gardez absolument à true
];
