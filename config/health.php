<?php

/*
| حدود فحص الصحة `php artisan ejaz:health` — docs/monitoring.md
*/

return [

    // أقصى عدد للمهام المنتظرة في الطابور قبل التنبيه (العمال متوقفون أو لا يلحقون)
    'queue_max_backlog' => (int) env('HEALTH_QUEUE_MAX_BACKLOG', 1000),

    // أقصى عدد للمهام الفاشلة في آخر 24 ساعة قبل التنبيه
    'failed_jobs_max' => (int) env('HEALTH_FAILED_JOBS_MAX', 0),

    // أقل نسبة مساحة حرة على قرص التخزين المحلي
    'disk_min_free_percent' => (int) env('HEALTH_DISK_MIN_FREE_PERCENT', 10),

    // أقصى مدة بالدقائق بلا نبض من المجدول (cron يشغّله كل دقيقة)
    'schedule_max_silence_minutes' => (int) env('HEALTH_SCHEDULE_MAX_SILENCE_MINUTES', 5),

];
