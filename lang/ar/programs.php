<?php

/*
| نصوص البرامج والدورات: «برامجنا»، وصفحة البرنامج، والتسجيل، و«برامجي»، وشاشات اللوحة.
*/

return [

    'title' => 'برامجنا',
    'intro_missing' => 'العنوان الفرعي ووصف صفحة البرامج المعتمدان من الجمعية',

    'nav' => [
        'programs' => 'البرامج المسجل بها',
        'programs_description' => 'برامجك ودوراتك ومواعيدها وحالة تسجيلك.',
    ],

    'tabs' => [
        'all' => 'جميع البرامج',
        'label' => 'أنواع البرامج',
    ],

    'delivery' => [
        'in_person' => 'حضوري',
        'remote' => 'عن بعد',
        'hybrid' => 'مدمج',
    ],

    'statuses' => [
        'soon' => 'قريباً',
        'open' => 'مفتوح',
        'full' => 'مكتمل',
        'ended' => 'منتهٍ',
    ],

    'registration_statuses' => [
        'confirmed' => 'مؤكد',
        'waitlisted' => 'في قائمة الانتظار',
        'cancelled' => 'ملغى',
    ],

    'list' => [
        'empty' => 'لا توجد برامج منشورة بعد.',
        'register' => 'سجّل الآن',
        'details' => 'التفاصيل',
    ],

    'show' => [
        'starts_at' => 'الموعد',
        'ends_at' => 'الانتهاء',
        'delivery' => 'طريقة التنفيذ',
        'location' => 'المكان',
        'seats' => 'المقاعد',
        'seats_left' => ':count من :total متاحة',
        'unlimited' => 'غير محدودة',
        'audience' => 'الفئة المستهدفة',
        'conditions' => 'شروط التسجيل',
        'initiative' => 'المبادرة',
        'registration_window' => 'التسجيل',
        'opens_at' => 'يُفتح في :date',
        'closes_at' => 'يُغلق في :date',
        'register' => 'سجّل الآن',
        'join_waitlist' => 'انضم لقائمة الانتظار',
        'login_to_register' => 'سجّل دخولك للتسجيل',
        'your_registration' => 'حالة تسجيلك: :status',
        'meeting' => 'رابط الحضور عن بعد',
        'meeting_hint' => 'الرابط لك وحدك، فلا تشاركه.',
        'meeting_pending' => 'يصلك رابط الحضور هنا قبل الموعد.',
        'manage' => 'إدارة تسجيلي',
        'closed' => 'التسجيل غير متاح لهذا البرنامج.',
    ],

    'register' => [
        'title' => 'التسجيل في: :program',
        'confirm' => 'تأكيد التسجيل',
        'no_fields' => 'لا بيانات إضافية لهذا البرنامج. أكّد تسجيلك.',
        'waitlist_hint' => 'المقاعد ممتلئة، وسيُدرج تسجيلك في قائمة الانتظار، ويصلك بريد إن توفر مقعد.',
    ],

    'mine' => [
        'title' => 'البرامج المسجل بها',
        'empty' => 'لم تسجل في أي برنامج بعد.',
        'browse' => 'تصفح البرامج',
        'cancel' => 'إلغاء التسجيل',
        'cancel_confirm' => 'هل تريد إلغاء تسجيلك في هذا البرنامج؟',
        'attended' => 'حضرت',
    ],

    'registered' => 'تم تسجيلك، وأرسلنا التأكيد إلى بريدك.',
    'waitlisted' => 'أُدرج تسجيلك في قائمة الانتظار، وسيصلك بريد إن توفر مقعد.',
    'cancelled' => 'أُلغي تسجيلك.',

    'errors' => [
        'closed' => 'التسجيل في هذا البرنامج غير متاح الآن.',
        'already_registered' => 'أنت مسجل في هذا البرنامج.',
        'full' => 'اكتملت المقاعد في هذا البرنامج.',
        'not_started' => 'لا يُسجَّل الحضور قبل بدء البرنامج.',
        'ends_before_start' => 'موعد الانتهاء قبل موعد البدء.',
        'closes_before_opens' => 'إغلاق التسجيل قبل فتحه.',
    ],

    'mail' => [
        'confirmed' => [
            'subject' => 'تأكيد تسجيلك في: :program',
            'line' => 'تم تسجيلك في «:program».',
        ],
        'waitlisted' => [
            'subject' => 'تسجيلك في قائمة الانتظار: :program',
            'line' => 'أُدرج تسجيلك في قائمة الانتظار لبرنامج «:program»، وسنراسلك إن توفر مقعد.',
        ],
        'promoted' => [
            'subject' => 'توفر لك مقعد في: :program',
            'line' => 'توفر مقعد في «:program»، وتأكد تسجيلك.',
        ],
        'reminder' => [
            'subject' => 'تذكير بموعد: :program',
            'line' => 'نذكّرك بموعد «:program».',
        ],
        'when' => 'الموعد: :date',
        'action' => 'تفاصيل البرنامج',
    ],

    // لوحة الإدارة
    'types' => [
        'model' => 'نوع برامج',
        'plural' => 'أنواع البرامج',
        'fields' => [
            'name' => 'الاسم',
            'sort' => 'الترتيب',
            'programs_count' => 'البرامج',
        ],
    ],

    'admin' => [
        'model' => 'برنامج',
        'plural' => 'البرامج',
        'sections' => [
            'basics' => 'البيانات الأساسية',
            'schedule' => 'المواعيد والتنفيذ',
            'registration' => 'التسجيل',
            'form_fields' => 'حقول التسجيل الإضافية',
            'form_fields_hint' => 'ما يملؤه المسجل عند التسجيل. الاسم والبريد والجوال من حسابه.',
        ],
        'fields' => [
            'title' => 'العنوان',
            'type' => 'النوع',
            'initiative' => 'المبادرة المرتبطة',
            'summary' => 'الوصف المختصر',
            'description' => 'التفاصيل',
            'image' => 'الصورة',
            'starts_at' => 'موعد البدء',
            'ends_at' => 'موعد الانتهاء',
            'delivery' => 'طريقة التنفيذ',
            'location' => 'المكان',
            'meeting_url' => 'رابط الاجتماع (تيمز)',
            'meeting_url_hint' => 'يظهر للمسجلين المؤكدين وحدهم في صفحة البرنامج.',
            'seats' => 'عدد المقاعد',
            'seats_hint' => 'فارغ لعدد غير محدود.',
            'audience' => 'الفئة المستهدفة',
            'conditions' => 'شروط التسجيل',
            'registration_opens_at' => 'فتح التسجيل',
            'registration_opens_at_hint' => 'فارغ ليُفتح فور النشر.',
            'registration_closes_at' => 'إغلاق التسجيل',
            'registration_closes_at_hint' => 'فارغ ليبقى مفتوحاً حتى بدء البرنامج.',
            'has_waitlist' => 'قائمة انتظار عند الامتلاء',
            'status' => 'الحالة',
            'confirmed' => 'المؤكدون',
        ],
    ],

    'registrations' => [
        'model' => 'مسجل',
        'plural' => 'المسجلون',
        'fields' => [
            'name' => 'الاسم',
            'email' => 'البريد',
            'phone' => 'الجوال',
            'status' => 'الحالة',
            'attended_at' => 'الحضور',
            'registered_at' => 'تاريخ التسجيل',
            'answers' => 'البيانات الإضافية',
            'program' => 'البرنامج',
        ],
        'actions' => [
            'mark_attended' => 'تسجيل الحضور',
            'unmark_attended' => 'إلغاء الحضور',
            'export' => 'تصدير المسجلين',
        ],
        'attendance_saved' => 'حُفظ الحضور لـ :count مسجل.',
        'export_done' => 'اكتمل تصدير :count مسجل.',
        'export_failed' => 'تعذّر تصدير :count مسجل.',
    ],

    // شهادة الحضور — قالب مؤقت حتى يصل قالب الجمعية
    'certificate' => [
        'title' => 'شهادة حضور',
        'temporary' => 'قالب مؤقت للتجربة — النص والتصميم والتوقيع المعتمدة تصل من الجمعية',
        'name' => 'الاسم',
        'program' => 'البرنامج',
        'date' => 'التاريخ',
        'missing' => 'نص الشهادة والتوقيع والختم المعتمدة من الجمعية',
        'download' => 'تنزيل شهادة الحضور',
        'issued' => 'الشهادة',
    ],

];
