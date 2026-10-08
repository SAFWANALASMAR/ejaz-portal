<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    // مزود الرسائل النصية: log في التطوير، والمزود الفعلي بعد تعاقد الجمعية
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'sender' => env('SMS_SENDER'),
        // إشعار المتقدم برسالة نصية عند تغيّر حالة طلبه (requirements/02)، مع البريد. معطل افتراضياً
        'request_updates' => (bool) env('SMS_REQUEST_UPDATES', false),
    ],

    // التحدي ضد الآليات على إرسال غير المسجلين: fake في التطوير والاختبارات، وturnstile (Cloudflare)
    // بمفتاحيه في الإنتاج (docs/decisions.md)
    'bot_challenge' => [
        'driver' => env('BOT_CHALLENGE_DRIVER', 'fake'),
        'turnstile_site_key' => env('TURNSTILE_SITE_KEY'),
        'turnstile_secret_key' => env('TURNSTILE_SECRET_KEY'),
    ],

    // اجتماعات تيمز للجمعية العمومية: fake في التطوير والاختبارات، وgraph (Microsoft Graph بمفتاح تطبيق
    // الجمعية في Microsoft 365) في الإنتاج. والاجتماع حدث في تقويم حساب المنظِّم (docs/decisions.md)
    'online_meetings' => [
        'driver' => env('ONLINE_MEETINGS_DRIVER', 'fake'),
        'graph' => [
            'tenant_id' => env('MS_GRAPH_TENANT_ID'),
            'client_id' => env('MS_GRAPH_CLIENT_ID'),
            'client_secret' => env('MS_GRAPH_CLIENT_SECRET'),
            'organizer' => env('MS_GRAPH_ORGANIZER'),
        ],
    ],

];
