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
    | قرص الملفات الخاصة (وثائق الحوكمة وملفات المكتبة): لا تُقدَّم إلا عبر مسار يفحص النشر
    | أو الصلاحية. محلي في التطوير، وتخزين متوافق مع S3 داخل المملكة في الإنتاج.
    */
    'private_disk' => env('FILESYSTEM_PRIVATE_DISK', 'local'),

    // صورة على القرص العام لا يشير إليها سجل تُحذف بعد هذه الأيام (ejaz:prune-public-files)
    'public_orphan_days' => (int) env('PUBLIC_ORPHAN_DAYS', 7),

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
            // تخزين متوافق مع S3 لدى مزود غير AWS قد يرفض مجاميع التحقق الإضافية في الإصدارات الحديثة من
            // مكتبة AWS، فتُضبط when_required عنده (docs/deploy/provider-checklist.md)
            'request_checksum_calculation' => env('AWS_REQUEST_CHECKSUM_CALCULATION', 'when_supported'),
            'response_checksum_validation' => env('AWS_RESPONSE_CHECKSUM_VALIDATION', 'when_supported'),
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
