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

    // مزود الرسائل النصية من البيئة: log في التطوير (يرفض الإنتاج)، أو مزود سعودي بمفتاحه. والمعتاد ضبطه من اللوحة
    'sms' => [
        // log أو taqnyat أو unifonic أو msegat. والمضبوط من «قنوات التواصل» في اللوحة يتقدم على هذه كلها
        'driver' => env('SMS_DRIVER', 'log'),
        'sender' => env('SMS_SENDER'),
        'api_key' => env('SMS_API_KEY'),
        // اسم حساب Msegat وحده
        'username' => env('SMS_USERNAME'),
        // إشعار المتقدم برسالة نصية عند تغيّر حالة طلبه (requirements/02)، مع البريد. معطل افتراضياً
        'request_updates' => (bool) env('SMS_REQUEST_UPDATES', false),
    ],

    // واتساب: log يكتب في السجل (يرفض الإنتاج)، وfake للاختبارات، وcloud (WhatsApp Cloud API من Meta) برسائل قوالب
    // معتمدة. والمضبوط من «قنوات التواصل» في اللوحة يتقدم على هذه كلها (docs/guides/messaging.md)
    'whatsapp' => [
        'driver' => env('WHATSAPP_DRIVER', 'log'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
        'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v23.0'),
        'language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'ar'),
        'templates' => [
            'exam_receipt' => env('WHATSAPP_TEMPLATE_EXAM_RECEIPT'),
            // قالب «المصادقة» عند Meta بزر «نسخ الرمز»: متغيره الرمز وحده (docs/guides/messaging.md)
            'otp' => env('WHATSAPP_TEMPLATE_OTP'),
        ],
        // رموز التحقق (توثيق الجوال واستعادة كلمة المرور) عبر واتساب بدل الرسائل النصية (قرار صفوان، 6 نوفمبر 2026)
        'otp' => (bool) env('WHATSAPP_OTP', false),
    ],

    // التحدي ضد الآليات على إرسال غير المسجلين: fake في التطوير والاختبارات، وturnstile (Cloudflare)
    // بمفتاحيه في الإنتاج (docs/decisions.md)
    'bot_challenge' => [
        'driver' => env('BOT_CHALLENGE_DRIVER', 'fake'),
        'turnstile_site_key' => env('TURNSTILE_SITE_KEY'),
        'turnstile_secret_key' => env('TURNSTILE_SECRET_KEY'),
    ],

    // اجتماعات تيمز للجمعية العمومية: fake في التطوير والاختبارات، وgraph (Microsoft Graph بمفتاح تطبيق
    // الجمعية في Microsoft 365) في الإنتاج. والاجتماع حدث في تقويم حساب المنظِّم (docs/decisions.md).
    // ومفاتيح graph من اللوحة لا من البيئة: «ربط تيمز» للمشرف العام، مطفأ حتى يُضبط (TeamsConnection)
    'online_meetings' => [
        'driver' => env('ONLINE_MEETINGS_DRIVER', 'fake'),
    ],

];
