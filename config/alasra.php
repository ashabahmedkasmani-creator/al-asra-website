<?php

return [
    'currency' => 'PKR',
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),
    'sanctum_token_ttl_minutes' => (int) env('SANCTUM_TOKEN_TTL_MINUTES', 10080),
    'trusted_proxies' => env('TRUSTED_PROXIES'),
    'admin' => ['name' => env('ADMIN_NAME', 'Al Asra Administrator'), 'email' => env('ADMIN_EMAIL', 'admin@alasra.local')],
    'max_line_quantity' => (int) env('ALASRA_MAX_LINE_QTY', 20),
    'max_cart_lines' => 50,

    // Whitelist of admin-editable settings. 'public' => exposed by GET /settings.
    'settings' => [
        'shipping_fee'            => ['default' => '250',   'rules' => ['required', 'numeric', 'min:0', 'max:100000'], 'public' => true],
        'free_shipping_threshold' => ['default' => '10000', 'rules' => ['required', 'numeric', 'min:0', 'max:10000000'], 'public' => true],
        'store_name'              => ['default' => 'Al-Asra Store', 'rules' => ['required', 'string', 'max:100'], 'public' => true],
        'tagline'                 => ['default' => 'Discover useful products, everyday essentials and carefully selected finds.', 'rules' => ['nullable', 'string', 'max:255'], 'public' => true],
        'contact_phone'           => ['default' => '+92 315 9259927', 'rules' => ['nullable', 'string', 'max:30'], 'public' => true],
        'contact_email'           => ['default' => 'support@alasra.online', 'rules' => ['nullable', 'email', 'max:150'], 'public' => true],
        'support_hours'           => ['default' => 'Mon–Sat, 11:00am–8:30pm', 'rules' => ['nullable', 'string', 'max:100'], 'public' => true],
        'store_city'              => ['default' => 'Karachi, Pakistan', 'rules' => ['nullable', 'string', 'max:100'], 'public' => true],
        'instagram_handle'        => ['default' => 'alasra.onlinestore', 'rules' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9._]+$/'], 'public' => true],
        'announcement'            => ['default' => 'Free delivery on orders over PKR 10,000 · Cash on delivery across Pakistan', 'rules' => ['nullable', 'string', 'max:255'], 'public' => true],
        'bank_account_title'      => ['default' => '', 'rules' => ['nullable', 'string', 'max:100'], 'public' => false],
    ],

    'provinces' => ['Sindh', 'Punjab', 'Khyber Pakhtunkhwa', 'Balochistan', 'Islamabad Capital Territory', 'Azad Jammu & Kashmir', 'Gilgit-Baltistan'],
];
