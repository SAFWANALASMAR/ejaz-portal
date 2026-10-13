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

    // نموذج «تواصل معنا» في الساعة: لكل مشترك (عنوان IPv4، أو /64 في IPv6)، ولكل موقع /48 في IPv6،
    // وللبوابة كلها حارساً أخيراً من الإغراق. ويُضاف إليها 5 رسائل في اليوم لكل صندوق بريد
    'contact_per_subscriber_per_hour' => (int) env('CONTACT_PER_SUBSCRIBER_PER_HOUR', 20),
    'contact_per_ipv6_site_per_hour' => (int) env('CONTACT_PER_IPV6_SITE_PER_HOUR', 60),
    'contact_per_hour' => (int) env('CONTACT_PER_HOUR', 300),

    // طلبات رابط استعادة كلمة المرور في الساعة لكل مشترك (عنوان IPv4، أو /64 في IPv6)، فوق حد Fortify
    // لكل بريد (دقيقة)، حتى لا يُرسل بريد استعادة لكل الحسابات من مصدر واحد
    'password_reset_per_subscriber_per_hour' => (int) env('PASSWORD_RESET_PER_SUBSCRIBER_PER_HOUR', 20),

    // حدان لكل شبكة في الساعة، حتى لا يستنزف مصدر واحد السقف العام:
    // - المعلّق (pending): رموز أُرسلت ولم يُتحقق منها. الرمز الجديد للمستخدم نفسه يحل محل
    //   السابق، والتحقق الناجح يحرر مكانه، فلا تتضرر الشبكات المشتركة كالمدارس والجوال.
    // - المرسل (sent): كل رسالة تُعدّ ولا تُحرَّر، فلا تضاعف إعادةُ الإرسال حدَّ الشبكة
    //   (للمستخدم خمس رسائل في الساعة).
    // IPv4: الشبكة /24. وIPv6: المشترك /64 بحد صارم، والموقع /48.
    // قاعدة الضبط: حد «المرسل» لكل شبكة لا يتجاوز عُشر sms_per_hour (اختبار يحرسها)
    'sms_pending_per_ipv4_network' => (int) env('SMS_PENDING_PER_IPV4_NETWORK', 200),
    'sms_sent_per_ipv4_network' => (int) env('SMS_SENT_PER_IPV4_NETWORK', 500),
    'sms_pending_per_ipv6_subscriber' => (int) env('SMS_PENDING_PER_IPV6_SUBSCRIBER', 20),
    'sms_sent_per_ipv6_subscriber' => (int) env('SMS_SENT_PER_IPV6_SUBSCRIBER', 25),
    'sms_pending_per_ipv6_site' => (int) env('SMS_PENDING_PER_IPV6_SITE', 200),
    'sms_sent_per_ipv6_site' => (int) env('SMS_SENT_PER_IPV6_SITE', 500),

    // أقصى انتظار بالثواني لقفل التحقق من رمز الجوال عند الطلبات المتزامنة
    'otp_lock_wait_seconds' => 3,

    // مدة خمول جلسة لوحة الإدارة بالدقائق قبل تسجيل الخروج آلياً
    'admin_idle_minutes' => (int) env('ADMIN_IDLE_MINUTES', 30),

    // HSTS يُرسل فقط على الاتصال الآمن
    'hsts_max_age' => 31536000,

    // مصادر خارجية مسموحة في سياسة أمان المحتوى (CSP)
    'csp' => [
        // تضمين مقاطع المكتبة والبث، من youtube-nocookie.com وحده (www.youtube.com رابط خارجي لا يُضمَّن)
        'frame_src' => [
            'https://www.youtube-nocookie.com',
        ],
        // عنصر التحدي (Turnstile): يُضاف إلى script-src وframe-src في صفحة الاستبيان وحدها، ومتى كان
        // مشغّل التحدي turnstile (SecurityHeaders)
        'challenge_src' => ['https://challenges.cloudflare.com'],
        // التخزين السحابي للرفع المباشر يضاف هنا عند تفعيله
        'connect_src' => array_filter(explode(',', (string) env('CSP_CONNECT_SRC', ''))),
        'media_src' => array_filter(explode(',', (string) env('CSP_MEDIA_SRC', ''))),
        // نطاقات الصور الخارجية (التخزين السحابي وشبكة التوصيل عند اختيارها)، مفصولة بفاصلة
        'img_src' => array_filter(explode(',', (string) env('CSP_IMG_SRC', ''))),
        'form_action' => array_filter(explode(',', (string) env('CSP_FORM_ACTION', ''))),
    ],

];
