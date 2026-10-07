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
    ],

];
