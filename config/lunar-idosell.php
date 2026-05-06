<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Idosell credentials
    |--------------------------------------------------------------------------
    |
    | Generate the REST API key in the Idosell merchant panel under
    | Configuration → API → API Keys. The base URL is your shop's subdomain
    | (e.g. `https://your-shop.idosell.com`), without trailing slash.
    |
    */
    'api_base_url' => env('IDOSELL_API_BASE_URL'),
    'api_key' => env('IDOSELL_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Sync targets
    |--------------------------------------------------------------------------
    |
    |   shop_id          — which Idosell shop (multi-shop accounts) to pull
    |                      products from. Defaults to 1 (single-shop setup).
    |   warehouse_id     — which Idosell warehouse to read stock levels from.
    |   language         — which Idosell translation to import (single language).
    |   lunar_channel_id — Lunar Channel into which products land. Defaults
    |                      to 1 (Lunar's default channel).
    |
    */
    'shop_id' => env('IDOSELL_SHOP_ID', 1),
    'warehouse_id' => env('IDOSELL_WAREHOUSE_ID', 1),
    'language' => env('IDOSELL_LANGUAGE', 'pl'),
    'lunar_channel_id' => env('IDOSELL_LUNAR_CHANNEL_ID', 1),

    /*
    |--------------------------------------------------------------------------
    | Sync behaviour
    |--------------------------------------------------------------------------
    |
    |   orders_on_placed         — push order to Idosell when Lunar fires
    |                              `OrderPlaced` event.
    |   orders_on_status_change  — push status update when Lunar Order
    |                              status changes.
    |   products_chunk_size      — how many products to fetch per Idosell
    |                              API call during the bulk sync.
    |
    */
    'sync' => [
        'orders_on_placed' => env('IDOSELL_PUSH_ORDERS_ON_PLACED', true),
        'orders_on_status_change' => env('IDOSELL_PUSH_ORDERS_ON_STATUS_CHANGE', true),
        'products_chunk_size' => (int) env('IDOSELL_PRODUCTS_CHUNK_SIZE', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Log channel
    |--------------------------------------------------------------------------
    */
    'log_channel' => env('IDOSELL_LOG_CHANNEL', 'stack'),
];
