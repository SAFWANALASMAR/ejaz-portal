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

        // مدة صلاحية روابط رفع الأجزاء الموقّعة بالدقائق. وبعدها يعيد طلب الاستئناف روابط جديدة
        'url_minutes' => (int) env('COMPETITION_VIDEO_URL_MINUTES', 120),

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
        // السؤال المقالي مبني ومطفأ بقرار المدير التنفيذي، وتصحيحه يدوي حين يُفعَّل
        'essay_enabled' => (bool) env('COMPETITION_STUDENT_ESSAY', false),

        // هامش بالثواني بعد الموعد النهائي يُقبل فيه حفظ الإجابة الأخيرة والتسليم (تأخر الشبكة)
        'grace_seconds' => (int) env('COMPETITION_STUDENT_GRACE_SECONDS', 20),

        // حدود تقنية لعدد أسئلة المرحلة ومدتها بالدقائق، والقيم الفعلية لكل مرحلة من اللوحة
        'max_questions' => 200,
        'min_minutes' => 5,
        'max_minutes' => 180,

        // سقف الحرف في إجابة المقالي
        'essay_max_chars' => 5000,

        // صورة خلفية الشهادة التي يرفعها المسؤول (ميجابايت)
        'certificate_background_max_kb' => 5120,
    ],

];
