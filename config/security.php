<?php

/*
| إعدادات الحماية الخاصة بالبوابة — انظر docs/security.md
*/

return [

    // مدة خمول جلسة لوحة الإدارة بالدقائق قبل تسجيل الخروج آلياً
    'admin_idle_minutes' => (int) env('ADMIN_IDLE_MINUTES', 30),

    // HSTS يُرسل فقط على الاتصال الآمن
    'hsts_max_age' => 31536000,

    // مصادر خارجية مسموحة في سياسة أمان المحتوى (CSP)
    'csp' => [
        // تضمين مقاطع المكتبة والبث
        'frame_src' => [
            'https://www.youtube-nocookie.com',
            'https://www.youtube.com',
        ],
        // التخزين السحابي للرفع المباشر يضاف هنا عند تفعيله
        'connect_src' => array_filter(explode(',', (string) env('CSP_CONNECT_SRC', ''))),
        'media_src' => array_filter(explode(',', (string) env('CSP_MEDIA_SRC', ''))),
        'form_action' => array_filter(explode(',', (string) env('CSP_FORM_ACTION', ''))),
    ],

];
