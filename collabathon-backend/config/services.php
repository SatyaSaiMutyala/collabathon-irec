<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Firebase Cloud Messaging
    |--------------------------------------------------------------------------
    | Path is relative to storage_path(). The service account JSON lives under
    | storage/app, which is gitignored, so the key is never committed. Absent
    | file => App\Services\Fcm no-ops instead of throwing.
    */
    'fcm' => [
        'credentials' => env('FCM_CREDENTIALS', 'app/firebase/service-account.json'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Maps
    |--------------------------------------------------------------------------
    | One key, two uses: the Maps JavaScript API loads it client-side (see
    | layouts/admin.blade.php — `window.GOOGLE_MAPS_API_KEY`), and
    | GeocodeController calls the Geocoding REST API with it server-side.
    | Those two uses want different key restrictions in Google Cloud Console
    | (HTTP referrer for the JS load, IP for the server call) and a single key
    | cannot carry both restriction types at once — this is fine to ship on
    | one key, but splitting into two keys later (one referrer-restricted to
    | this domain, one IP-restricted to the server) is the tighter setup.
    */
    'google_maps' => [
        'key' => env('GOOGLE_MAPS_API_KEY'),
    ],


    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
