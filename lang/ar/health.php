<?php

return [
    'checks' => [
        'database' => 'قاعدة البيانات',
        'cache' => 'الذاكرة المؤقتة',
        'queue' => 'الطابور',
        'failed_jobs' => 'المهام الفاشلة',
        'disk' => 'مساحة القرص',
        'private_storage' => 'التخزين الخاص',
        'schedule' => 'المجدول',
    ],
    'ok' => 'سليم',
    'failed' => [
        'cache' => 'القيمة المكتوبة لم تُقرأ',
        'queue' => ':size مهمة تنتظر (الحد :max)',
        'failed_jobs' => ':count مهمة فشلت في آخر 24 ساعة (الحد :max)',
        'disk' => 'المساحة الحرة :free% (الحد :min%)',
        'private_storage' => 'الملف المكتوب لم يُقرأ',
        'schedule_missing' => 'لا نبض من المجدول: هل مهمة cron تعمل؟',
        'schedule_stale' => 'آخر نبض قبل :minutes دقيقة',
    ],
    'summary_ok' => 'كل الفحوص سليمة.',
    'summary_failed' => 'فشل :count من الفحوص.',
];
