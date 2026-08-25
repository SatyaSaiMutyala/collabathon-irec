<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        /*
         * Where user uploads actually go — the two disks the app writes through.
         *
         * `uploads` is world-readable material: property photos and plans, developer
         * logos, a channel partner's profile picture. `secure` is the KYC set — PAN,
         * Aadhaar (scan and XML), RERA, GST, cancelled cheque, signature — which stays
         * private in the bucket and is only ever reached through a short-lived signed
         * URL. Splitting them is the point: a marketing photo and a cancelled cheque
         * should not sit behind the same permissions.
         *
         * Both follow FILESYSTEM_DISK, so `local` keeps development writing to
         * storage/app/public exactly as before, and `s3` moves everything to the bucket
         * with no code change. App\Support\FileStorage decides which of the two any
         * given path belongs to — nothing else should name these disks directly.
         *
         * On S3 the public/private split is a key prefix — `public/…` and `private/…`
         * — set through each disk's `root`. The path stored in the database is
         * unchanged either way (it stays `properties/12/cover.jpg`), so nothing has to
         * be rewritten there; the prefix exists so that one bucket policy can open
         * exactly the public half and nothing else. Renaming these prefixes without
         * updating that policy makes every image 403.
         *
         * Note the absence of any 'visibility' key on the S3 branches. The bucket is
         * BucketOwnerEnforced, which disables ACLs outright: a PutObject carrying
         * `x-amz-acl: public-read` is rejected with AccessControlListNotSupported, so
         * asking Flysystem for public visibility would fail every upload rather than
         * make anything public. Public read comes from the bucket policy instead —
         * see docs/AWS-S3-SETUP.md.
         */
        'uploads' => env('FILESYSTEM_DISK', 'local') === 's3'
            ? [
                'driver' => 's3',
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
                'region' => env('AWS_DEFAULT_REGION'),
                'bucket' => env('AWS_BUCKET'),
                'root' => env('AWS_PUBLIC_PREFIX', 'public'),
                // Point AWS_URL at a CloudFront distribution to serve these through the
                // CDN; left unset they resolve to the bucket's own regional endpoint.
                'url' => env('AWS_URL'),
                'endpoint' => env('AWS_ENDPOINT'),
                'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
                'throw' => false,
                'report' => false,
            ]
            : [
                'driver' => 'local',
                'root' => storage_path('app/public'),
                'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
                'visibility' => 'public',
                'throw' => false,
                'report' => false,
            ],

        'secure' => env('FILESYSTEM_DISK', 'local') === 's3'
            ? [
                'driver' => 's3',
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
                'region' => env('AWS_DEFAULT_REGION'),
                'bucket' => env('AWS_BUCKET'),
                'root' => env('AWS_PRIVATE_PREFIX', 'private'),
                'endpoint' => env('AWS_ENDPOINT'),
                'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
                // Deliberately no 'url': a private object has no publicly resolvable
                // address, and configuring one invites url() being called somewhere
                // temporaryUrl() is required. No 'visibility' either, for the same
                // ACLs-are-disabled reason as `uploads`; these objects are private
                // because nothing in the bucket policy grants them, which is the
                // default and cannot be undone by a stray write.
                'throw' => false,
                'report' => false,
            ]
            : [
                // Development keeps these under the same local root, so paths already
                // in the database keep resolving. The split is a real boundary only on S3.
                'driver' => 'local',
                'root' => storage_path('app/public'),
                'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
                'throw' => false,
                'report' => false,
            ],


        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
