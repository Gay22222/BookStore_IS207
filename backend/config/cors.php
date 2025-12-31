<?php

return [
    // Áp dụng cho API & (tuỳ chọn) route csrf của Sanctum
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Lấy từ ENV để tiện đổi theo môi trường
    // Một domain:
    // 'allowed_origins' => [env('FRONTEND_URL', 'https://example.com')],
    // Nhiều domain (phân tách bởi dấu phẩy):
    'allowed_origins' => array_map('trim', explode(',', env('APP_FRONTEND', env('APP_FRONTEND', '')))),

    // Hoặc dùng pattern cho subdomain: ['*.your-domain.com']
    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],
    'exposed_headers' => ['Authorization'],
    'max_age' => 0,

    // Nếu frontend dùng cookie (Sanctum) đặt true; nếu dùng Bearer/JWT, để false
    'supports_credentials' => env('CORS_CREDENTIALS', false),
];
