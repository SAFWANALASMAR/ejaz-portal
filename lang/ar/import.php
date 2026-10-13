<?php

/*
| نصوص أمر استيراد محتوى الموقع الحالي (php artisan import:legacy-site).
*/

return [

    'sections' => [
        'media' => 'مكتبة الوسائط',
        'pages' => 'الصفحات',
        'initiatives' => 'المبادرات',
        'documents' => 'الوثائق',
        'library' => 'المكتبة',
        'settings' => 'الإعدادات',
        'redirects' => 'التحويلات',
    ],

    'outcomes' => [
        'created' => 'أُنشئ مسودة',
        'filled' => 'مُلئت حقوله الفارغة: :fields',
        'unchanged' => 'موجود، ولا حقل فارغاً فيه يُملأ',
        'published' => 'منشور، فلم يُمس',
        'deleted' => 'محذوف في اللوحة، فلم يُستورد',
        'rejected' => 'رُفض: :reason',
        'redirect_created' => 'تحويل :from إلى :to',
        'redirect_exists' => 'لـ :from تحويل مسجل من قبل',
    ],

    'summary' => 'الخلاصة: أُنشئ :created، ومُلئ :filled، وتُرك :skipped، ورُفض :rejected، وتحويلات جديدة :redirects.',
    'dry_run_done' => 'تجربة فقط: أُلغي كل ما سبق، ولم يُحفظ شيء.',

    'errors' => [
        'actor' => 'حدد بريد مشرف نشط بالخيار --actor.',
        'forbidden' => 'لا يملك هذا الحساب صلاحية الإنشاء في: :sections.',
        'file' => 'تعذّر قراءة ملف الاستيراد، أو ليس JSON صالحاً.',
        'structure' => 'بنية ملف الاستيراد غير صحيحة: :reason',
        'file_missing' => 'الملف :file غير موجود داخل مجلد ملف الاستيراد.',
        'file_size' => 'الملف :file أكبر من الحد المسموح.',
        'video_file' => 'المقطع المرئي برابط في video_url، لا بملف.',
        'media_type' => 'الملف :file ليس صورة JPG أو PNG أو WebP، ولا أيقونة SVG سليمة.',
        'media_missing' => 'لا عنصر في مكتبة الوسائط بالمعرّف :media.',
    ],

];
