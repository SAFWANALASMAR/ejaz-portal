<?php

/*
| إعدادات الحماية الخاصة بالبوابة — انظر docs/security.md
*/

return [

    // عناوين موزعات الأحمال الموثوقة (مفصولة بفاصلة، أو * خلف موزع مُدار)،
    // حتى يرى التطبيق عنوان الزائر الحقيقي من رؤوس X-Forwarded-*
    'trusted_proxies' => env('TRUSTED_PROXIES'),

    // حد التسجيل من عنوان واحد في الساعة. مرتفع عمداً لأن شبكات الجوال (CGNAT)
    // والمدارس والجامعات تجمع آلاف المستخدمين خلف عنوان واحد. الحماية الأساسية
    // من الحسابات الآلية هي توثيق الجوال (docs/decisions.md)
    'registration_per_ip_per_hour' => (int) env('REGISTRATION_PER_IP_PER_HOUR', 60),

    // سقف رسائل التحقق النصية للبوابة كلها في الساعة، حماية من إغراق الرسائل وتكلفته.
    // يُضبط قبل كل فتح تسجيل كبير حسب العدد المتوقع، ويُختبر في اختبار التحمل (docs/performance.md)
    'sms_per_hour' => (int) env('SMS_PER_HOUR', 5000),

    // تنبيه المشرف العام عند هذه النسب من السقف، قبل التوقف عنده
    'sms_alert_thresholds' => [70, 90],

    // أقصى عدد رموز أُرسلت ولم يُتحقق منها من شبكة واحدة (/24) في الساعة.
    // التحقق الناجح يحرر مكانه، فلا تتضرر الشبكات المشتركة كالمدارس والجوال
    'sms_unverified_per_network' => (int) env('SMS_UNVERIFIED_PER_NETWORK', 200),

    // أقصى انتظار بالثواني لقفل التحقق من رمز الجوال عند الطلبات المتزامنة
    'otp_lock_wait_seconds' => 3,

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
