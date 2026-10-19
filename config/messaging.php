<?php

/*
| قنوات التواصل: الرسائل النصية وواتساب (docs/guides/messaging.md، docs/decisions.md — 19 أكتوبر 2026).
| المزود ومفاتيحه من «قنوات التواصل» في اللوحة، وإلا من .env (config/services.php: sms وwhatsapp).
*/

return [

    // مهلة طلب المزود بالثواني، ومهلة الاتصال وحده. والطابور يعيد المحاولة بعدها
    'timeout' => (int) env('MESSAGING_TIMEOUT', 10),
    'connect_timeout' => (int) env('MESSAGING_CONNECT_TIMEOUT', 5),

    // حد الرسائل لكل رقم في اليوم في كل قناة، لكل الاستعمالات معاً، حارساً أخيراً للتكلفة فوق حدود رموز
    // التحقق (SendPhoneVerificationCode: خمسة في الساعة وعشرة في اليوم للرقم، وحدود الشبكة). 0 بلا حد
    'per_number_per_day' => (int) env('MESSAGING_PER_NUMBER_PER_DAY', 20),

    // رسائل الاختبار من اللوحة لكل مشرف في الساعة، وللبوابة كلها في اليوم
    'tests_per_hour' => (int) env('MESSAGING_TESTS_PER_HOUR', 10),
    'tests_per_day' => (int) env('MESSAGING_TESTS_PER_DAY', 30),

    // إشعارات تسليم واتساب في الدقيقة لكل مشترك (IPv4، أو /64 في IPv6). واسع لأن Meta ترسل من عناوين قليلة
    'webhook_per_minute' => (int) env('MESSAGING_WEBHOOK_PER_MINUTE', 1000),

    // مدة الاحتفاظ بسجل حالة الرسائل بالأيام (model:prune يومياً)
    'log_days' => (int) env('MESSAGING_LOG_DAYS', 90),

    // الاستعمالات التي تُفعَّل من اللوحة، ومفتاح كل منها في .env قيمةً أولى حتى تُضبط من اللوحة
    'usages' => [
        'sms' => [
            'request_updates' => 'services.sms.request_updates',
        ],
        'whatsapp' => [
            'exam_receipt' => 'competitions.exam.whatsapp',
        ],
    ],

    // عناوين المزودين (من وثائقهم المنشورة، docs/guides/messaging.md)
    'endpoints' => [
        'taqnyat' => 'https://api.taqnyat.sa/v1/messages',
        'unifonic' => 'https://el.cloud.unifonic.com/rest/SMS/messages',
        'msegat' => 'https://www.msegat.com/gw/sendsms.php',
        'whatsapp' => 'https://graph.facebook.com',
    ],

];
