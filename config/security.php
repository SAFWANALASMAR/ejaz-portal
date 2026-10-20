<?php

/*
| إعدادات الحماية الخاصة بالبوابة — انظر docs/security.md
*/

return [

    // عناوين موزعات الأحمال الموثوقة مفصولة بفاصلة (الأفضل)، حتى يرى التطبيق عنوان الزائر الحقيقي
    // من رؤوس X-Forwarded-*. و* تصدّق رأس X-Forwarded-For من أي مصدر، فلا تُستعمل إلا والخادم معزول
    // في الشبكة لا يصله إلا الموزع، والموزع يستبدل الرأس كاملاً، وإلا زُوّرت حدود الشبكات برأس.
    // وفارغة إن وصل الزوار إلى الخادم مباشرة. وسكربتا النشر ينبّهان إلى الفارغة و* (docs/security.md)
    'trusted_proxies' => env('TRUSTED_PROXIES'),

    // حدود التسجيل (CreateNewUser — قرار صفوان، 20 أكتوبر 2026، أشد من الحد الوحيد السابق 60 في الساعة). شبكات الجوال
    // (CGNAT) والمدارس والجامعات تجمع آلاف المستخدمين خلف عنوان واحد، فالحماية الأولى التحدي ضد الآليات (Turnstile)
    // وتوثيق الجوال، والحدود سقوف فوقها تبقى الشبكات المشتركة ممكنة معها:
    // - لكل مشترك (عنوان IPv4 أو /64 في IPv6) في الساعة.
    'registration_per_ip_per_hour' => (int) env('REGISTRATION_PER_IP_PER_HOUR', 30),
    // - لكل جهاز (المشترك مع بصمة متصفحه) في الساعة، فلا يُكثر جهاز واحد خلف الشبكة المشتركة.
    'registration_per_device_per_hour' => (int) env('REGISTRATION_PER_DEVICE_PER_HOUR', 5),
    // - لكل شبكة (/24 في IPv4، و/48 في IPv6) في اليوم، فلا يُلتف على حد المشترك بتدوير العناوين.
    'registration_per_network_per_day' => (int) env('REGISTRATION_PER_NETWORK_PER_DAY', 300),
    // - للبوابة كلها في الساعة، يُنبَّه المشرف العام عند بلوغه. يُرفع قبل فتح تسجيل كبير كسقف الرسائل.
    'registration_per_hour' => (int) env('REGISTRATION_PER_HOUR', 3000),

    // سقف رسائل التحقق النصية للبوابة كلها في الساعة، حماية من إغراق الرسائل وتكلفته.
    // يُضبط قبل كل فتح تسجيل كبير حسب العدد المتوقع، ويُختبر في اختبار التحمل (docs/performance.md)
    'sms_per_hour' => (int) env('SMS_PER_HOUR', 5000),

    // تنبيه المشرف العام عند هذه النسب من السقف، قبل التوقف عنده
    'sms_alert_thresholds' => [70, 90],

    // تنبيه المشرف العام بعد هذا العدد من رموز التحقق الثنائي الخاطئة على حساب في اليوم (0 يوقفه). وأقل من 30،
    // لأن محاولات التحقق الثنائي تتوقف عند 30 في اليوم (FortifyServiceProvider)
    'two_factor_alert_threshold' => (int) env('TWO_FACTOR_ALERT_THRESHOLD', 5),

    // رفع ملفات اللوحة المؤقت (Livewire): لكل حساب في الدقيقة وفي الساعة (0 بلا حد). يتسع لحقل ملفات متعددة (الأرشيف حتى 20)
    'uploads_per_minute' => (int) env('UPLOADS_PER_MINUTE', 30),
    'uploads_per_hour' => (int) env('UPLOADS_PER_HOUR', 300),

    // نموذج «تواصل معنا» في الساعة: لكل مشترك (عنوان IPv4، أو /64 في IPv6)، ولكل موقع /48 في IPv6،
    // وللبوابة كلها حارساً أخيراً من الإغراق. ويُضاف إليها 5 رسائل في اليوم لكل صندوق بريد
    'contact_per_subscriber_per_hour' => (int) env('CONTACT_PER_SUBSCRIBER_PER_HOUR', 20),
    'contact_per_ipv6_site_per_hour' => (int) env('CONTACT_PER_IPV6_SITE_PER_HOUR', 60),
    'contact_per_hour' => (int) env('CONTACT_PER_HOUR', 300),

    // محاولات الدخول الخاطئة في ربع ساعة لكل مشترك (عنوان IPv4، أو /64 في IPv6) ولكل موقع /48 في IPv6،
    // فوق قفل الحساب، حتى لا يخمّن مصدر واحد كلمات مرور حسابات كثيرة. وتتسع للشبكات المشتركة (CGNAT).
    // ومعهما حارسان لمعالج التجزئة في الدقيقة (0 بلا حد)، يُضبطان مع اختبار التحمل (docs/performance.md):
    // سقف عام للمحاولات الخاطئة في البوابة، والناجحة لا تُحسب منه فلا يملؤه صاحب حساب صحيح، وحد لكل
    // المحاولات من كل مشترك ناجحة أو خاطئة. والمحجوب يُرفض بـ 429 قبل فحص كلمة المرور
    'login_failures_per_subscriber' => (int) env('LOGIN_FAILURES_PER_SUBSCRIBER', 50),
    'login_failures_per_ipv6_site' => (int) env('LOGIN_FAILURES_PER_IPV6_SITE', 150),
    'login_failures_per_minute' => (int) env('LOGIN_FAILURES_PER_MINUTE', 1200),
    'login_attempts_per_subscriber_per_minute' => (int) env('LOGIN_ATTEMPTS_PER_SUBSCRIBER_PER_MINUTE', 300),

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

    // الحساب الذي لم يوثّق جواله يُحذف بعد هذه المدة بالأيام، ما لم يرتبط بسجل أو بدور غير المستفيد
    // (ejaz:prune-unverified-accounts — decisions.md 14 أكتوبر 2026)
    'unverified_account_days' => (int) env('UNVERIFIED_ACCOUNT_DAYS', 14),

    // أقصى انتظار بالثواني لقفل التحقق من رمز الجوال عند الطلبات المتزامنة
    'otp_lock_wait_seconds' => 3,

    // مدة خمول جلسة لوحة الإدارة بالدقائق قبل تسجيل الخروج آلياً
    'admin_idle_minutes' => (int) env('ADMIN_IDLE_MINUTES', 30),

    // سقف مطلق لجلسة لوحة الإدارة بالدقائق من أول طلب فيها، مهما استمر النشاط (8 ساعات)
    'admin_session_max_minutes' => (int) env('ADMIN_SESSION_MAX_MINUTES', 480),

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
        // نطاق التخزين لعرض ملف PDF من الحوكمة والمكتبة مدمجاً، متى قُدّم برابط موقّع (القرص الخاص على S3)
        'storage_frame_src' => array_filter(explode(',', (string) env('CSP_FRAME_SRC', ''))),
        // نطاقات الصور الخارجية (التخزين السحابي وشبكة التوصيل عند اختيارها)، مفصولة بفاصلة
        'img_src' => array_filter(explode(',', (string) env('CSP_IMG_SRC', ''))),
        'form_action' => array_filter(explode(',', (string) env('CSP_FORM_ACTION', ''))),
    ],

];
