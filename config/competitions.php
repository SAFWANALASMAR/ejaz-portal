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

        // حجم الجزء في الرفع المجزأ بالميجابايت. ويبقى دون حد الطلب في nginx وPHP
        'chunk_mb' => (int) env('COMPETITION_VIDEO_CHUNK_MB', 5),

        // مدة صلاحية روابط رفع الأجزاء الموقّعة بالدقائق. وبعدها يعيد طلب الاستئناف روابط جديدة
        'url_minutes' => (int) env('COMPETITION_VIDEO_URL_MINUTES', 120),

        // أداة فحص المدة الحقيقية. وفارغة: المدة من ترويسة MP4 وحدها (للاختبارات والتطوير بلا الأداة)
        'ffprobe' => (string) env('COMPETITION_VIDEO_FFPROBE', 'ffprobe'),
    ],

];
