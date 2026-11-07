<?php

/*
| إعدادات المسابقات التقنية (docs/plans/competitions.md). حدود الفيديو لكل سؤال يُدخلها مسؤول
| المسابقة من اللوحة، وهذه سقوف النظام فوقها.
*/

return [

    'video' => [
        // السقف الأعلى لحد الحجم الذي يُدخله المسؤول لسؤال الفيديو، بالميجابايت
        'max_mb' => (int) env('COMPETITION_VIDEO_MAX_MB', 2048),

        // السقف الأعلى لحد المدة بالثواني
        'max_seconds' => (int) env('COMPETITION_VIDEO_MAX_SECONDS', 3600),

        // حجم الجزء في الرفع المجزأ بالميجابايت. ويبقى دون حد الطلب في nginx وPHP، ولا يقل عن 5 في
        // الرفع إلى S3 (أصغر جزء يقبله غير الأخير)
        'chunk_mb' => (int) env('COMPETITION_VIDEO_CHUNK_MB', 5),

        // مدة صلاحية روابط رفع الأجزاء الموقّعة بالدقائق، وبعدها يعيد طلب الاستئناف روابط جديدة
        'url_minutes' => (int) env('COMPETITION_VIDEO_URL_MINUTES', 120),

        // مدة رابط مشاهدة المحكّم للمقطع على S3 (ReviewVideoController): قصيرة، فمقاطع طلاب قُصّر، ومن حصل على الرابط يشاهده
        // وحده حتى نهايته. والمشغّل يعيد طلب رابط البوابة عند الخطأ أو التوقف فيأخذ توقيعاً جديداً بعد الفحص (البلاغ 185، I2)
        'review_url_minutes' => (int) env('COMPETITION_REVIEW_VIDEO_URL_MINUTES', 5),

        // طريقة الرفع: app عبر خادم التطبيق، أو s3 مباشرة من المتصفح إلى القرص الخاص (يلزم أن يكون
        // قرص s3، وأن يُضاف نطاقه إلى CSP_CONNECT_SRC، وأن تُضبط CORS الحاوية — docs/decisions.md)
        'upload' => (string) env('COMPETITION_VIDEO_UPLOAD', 'app'),

        // أداة فحص المدة الحقيقية. وفارغة: المدة من ترويسة MP4 وحدها (للاختبارات والتطوير بلا الأداة)
        'ffprobe' => (string) env('COMPETITION_VIDEO_FFPROBE', 'ffprobe'),
    ],

    // الاختبار الإلكتروني (docs/plans/competition-exam.md)
    'exam' => [
        // رقم الهوية أو الإقامة في التسجيل: ينتظر قرار صفوان (نظام حماية البيانات الشخصية)، فمعطّل افتراضياً
        'national_id' => (bool) env('COMPETITION_EXAM_NATIONAL_ID', false),

        // مفتاح بصمة الرقم (HMAC) لمنع التكرار، مستقل عن APP_KEY. يلزم قبل التفعيل
        'national_id_key' => (string) env('COMPETITION_NATIONAL_ID_HMAC_KEY', ''),

        // إشعار واتساب بعد إرسال الاختبار: ينتظر قرار صفوان (المزوّد)، فمعطّل افتراضياً
        'whatsapp' => (bool) env('COMPETITION_EXAM_WHATSAPP', false),

        // هامش بالثواني بين مدة الفيديو التعريفي ووقت المشاهدة المسجل في الخادم (تأخر الشبكة وبدء التشغيل)
        'intro_tolerance_seconds' => (int) env('COMPETITION_EXAM_INTRO_TOLERANCE', 3),

        // سقف حجم الفيديو التعريفي ووسائط الأسئلة التي يرفعها المسؤول من اللوحة، بالميجابايت. ومنه حد الرفع
        // المؤقت في Livewire (config/livewire.php). وفي الإنتاج يذهب الرفع من المتصفح إلى التخزين مباشرة
        // (LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK=s3) فلا يمر بخادم التطبيق (decisions.md)
        'media_max_mb' => (int) env('COMPETITION_EXAM_MEDIA_MAX_MB', 200),

        // مدة بقاء أسئلة الاختبار في الذاكرة المؤقتة بالدقائق، وتُمسح عند كل حفظ للمسابقة
        'cache_minutes' => (int) env('COMPETITION_EXAM_CACHE_MINUTES', 60),

        // مدة رابط التخزين الموقّع للفيديو التعريفي ووسائط الأسئلة بالدقائق (في الإنتاج على S3)
        'media_url_minutes' => (int) env('COMPETITION_EXAM_MEDIA_URL_MINUTES', 5),
    ],

    // رفع الأعمال: سقوف تقنية لا محتوى للجمعية، تُضبط من .env بطلبها
    'works' => [
        'max_items' => (int) env('COMPETITION_WORKS_MAX_ITEMS', 10),
        'image_max_kb' => (int) env('COMPETITION_WORKS_IMAGE_MAX_KB', 10240),
        'pdf_max_kb' => (int) env('COMPETITION_WORKS_PDF_MAX_KB', 20480),
        'url_minutes' => (int) env('COMPETITION_WORKS_URL_MINUTES', 10),
    ],

    // مسابقة الطلاب (docs/plans/student-competition.md)
    'student' => [
        // هامش بالثواني بعد الموعد النهائي يُقبل فيه حفظ الإجابة الأخيرة والتسليم (تأخر الشبكة)
        'grace_seconds' => (int) env('COMPETITION_STUDENT_GRACE_SECONDS', 20),

        // عدد أسئلة المرحلة ومدتها بالدقائق بقرار المدير التنفيذي: 30–40 سؤالاً و30–45 دقيقة، والافتراضي 40 و45.
        // والقيمة لكل مرحلة من اللوحة ضمنها
        'min_questions' => 30,
        'max_questions' => 40,
        'default_questions' => 40,
        'min_minutes' => 30,
        'max_minutes' => 45,
        'default_minutes' => 45,

        // المراحل التي تُنشأ مع المسابقة (قرار المدير: أربع)، وتُضاف وتُحذف من اللوحة
        'default_rounds' => 4,

        // حدود العنوان الواحد أثناء نوافذ مسابقة الطلاب (SchoolNetworkLimits)، لمدرسة كاملة خلف عنوان واحد: التسجيل
        // ما دام مفتوحاً، والدخول في أيام الاختبار وحدها. ومقابلها خارج النافذة في config/security.php: 120 تسجيلاً في
        // الساعة، و5 لكل جهاز، و2000 لكل شبكة في اليوم، و50 دخولاً خاطئاً في ربع ساعة، و300 محاولة دخول في الدقيقة.
        // والجهاز 40 في الساعة: جهاز المعمل يسجّل فصلاً كاملاً، وحد الجهاز كعكة يحذفها المهاجم فلا يحمي منه أصلاً
        // (decisions.md، 26 أكتوبر)
        'window_limits' => [
            'registration_per_ip_per_hour' => (int) env('COMPETITION_WINDOW_REGISTRATION_PER_IP_PER_HOUR', 600),
            'registration_per_device_per_hour' => (int) env('COMPETITION_WINDOW_REGISTRATION_PER_DEVICE_PER_HOUR', 40),
            'registration_per_network_per_day' => (int) env('COMPETITION_WINDOW_REGISTRATION_PER_NETWORK_PER_DAY', 6000),
            'login_failures_per_subscriber' => (int) env('COMPETITION_WINDOW_LOGIN_FAILURES_PER_SUBSCRIBER', 300),
            'login_attempts_per_subscriber_per_minute' => (int) env('COMPETITION_WINDOW_LOGIN_ATTEMPTS_PER_SUBSCRIBER_PER_MINUTE', 900),
            // تعيين كلمة المرور برمز الرابط (password-update) أيام الاختبار، كالدخول (الاختبار الأمني 0.14.0)
            'password_update_per_subscriber_per_minute' => (int) env('COMPETITION_WINDOW_PASSWORD_UPDATE_PER_SUBSCRIBER_PER_MINUTE', 600),
            // فتحات قارئ دليل المسابقة (الكتاب المختار في صفحتها) من المشترك الواحد في الدقيقة، أثناء التسجيل وأيام الاختبار
            // (قرار صفوان، 7 نوفمبر 2026). والكتب الأخرى وخارج النوافذ بحد القارئ العادي (ReaderFileResponse::PER_MINUTE).
            // ويبقى فوقه حد المسار (throttle:120,1 في routes/web.php): لكل حساب للطالب المسجّل دخوله، ولكل عنوان للزائر
            'reader_guide_per_subscriber_per_minute' => (int) env('COMPETITION_WINDOW_READER_GUIDE_PER_SUBSCRIBER_PER_MINUTE', 300),
        ],

        // سقف الحرف في إجابة المقالي
        'essay_max_chars' => 5000,

        // صورة خلفية الشهادة التي يرفعها المسؤول (ميجابايت)
        'certificate_background_max_kb' => 5120,
    ],

];
