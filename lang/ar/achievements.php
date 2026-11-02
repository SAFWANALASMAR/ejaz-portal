<?php

// «إنجازاتنا» (docs/decisions/2026-11-02-achievements.md)
return [
    'model' => 'إنجاز',
    'plural' => 'إنجازاتنا',
    'title' => 'إنجازاتنا',

    'fields' => [
        'title' => 'عنوان الإنجاز',
        'achieved_on' => 'تاريخ الإنجاز',
        'details' => 'التفاصيل',
        'status' => 'حالة النشر',
        'attachment' => 'المرفق',
        'attachment_hint' => 'اختياري: صورة (JPG أو PNG أو WebP) أو ملف PDF حتى 20 ميجابايت. يُحفظ في القرص الخاص، ويظهر للزائر مع المنشور وحده.',
    ],

    'statuses' => [
        'draft' => 'مسودة',
        'published' => 'منشور',
    ],

    'actions' => [
        'attachment' => 'عرض المرفق',
        'add' => 'إضافة إنجاز',
        'manage' => 'إدارة الإنجازات',
        'open_image' => 'عرض الصورة',
        'download_file' => 'تنزيل الملف',
        'all' => 'كل الإنجازات',
    ],

    'timeline' => 'الخط الزمني للإنجازات',
    'this_year' => 'عدد الإنجازات هذا العام',
    'empty' => 'لا إنجازات منشورة بعد.',
    'portal_empty' => 'لا إنجازات منشورة بعد. تضيفها الإدارة التنفيذية من لوحة الإدارة.',

    'validation' => [
        'invalid_file' => 'المرفق يجب أن يكون PDF أو صورة (JPG أو PNG أو WebP) سليمة.',
    ],
];
