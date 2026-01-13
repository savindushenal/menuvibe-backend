<?php

return [
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],
    'vercel_blob' => [
        'token' => env('VERCEL_BLOB_WRITE_TOKEN'),
        'base_url' => env('VERCEL_BLOB_BASE_URL', 'https://blob.vercel-storage.com'),
        'prefix' => env('VERCEL_BLOB_PREFIX', 'logos'),
    ],
    'email_api' => [
        'base_url' => env('EMAIL_API_BASE_URL', 'https://email.absterco.com'),
        'api_key' => env('EMAIL_API_KEY'),
        'domain' => env('EMAIL_API_DOMAIN', 'menuvire.com'),
    ],
    'absterco' => [
        'api_key' => env('ABSTERCO_API_KEY'),
        'base_url' => env('ABSTERCO_BASE_URL', 'https://api.gateway.absterco.com'),
        'organization_id' => env('ABSTERCO_ORGANIZATION_ID'),
    ],
];