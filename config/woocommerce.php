<?php

return [
    'url' => env('WOOCOMMERCE_URL'),
    'consumer_key' => env('WOOCOMMERCE_CK'),
    'consumer_secret' => env('WOOCOMMERCE_CS'),
    'version' => env('WOOCOMMERCE_VERSION', 'wc/v3'),
    'per_page' => env('WOOCOMMERCE_PER_PAGE', 50),
];
