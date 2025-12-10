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
];