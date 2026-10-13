<?php

return [

    'title' => 'الجمعية العمومية',

    // عنصر لوحة المستخدم وبطاقته (الصورة 04)
    'nav' => 'الجمعية العمومية',
    'nav_description' => 'اجتماعات الجمعية العمومية ومحاضرها المعتمدة وملفات الأعضاء.',

    // صفحة القسم (الصورة 05)
    'index' => [
        'tiles' => 'أقسام منطقة الأعضاء',
        'login' => 'دخول الأعضاء',
        'meetings' => 'الاجتماعات',
        'minutes' => 'المحاضر',
        'files' => 'الملفات',
        'not_member' => 'منطقة الأعضاء لأعضاء الجمعية العمومية ومجلس الإدارة.',
        'join' => 'طلب الانضمام للجمعية العمومية',
        'next_meeting' => 'الاجتماع القادم',
    ],

    'meetings' => [
        'title' => 'الاجتماعات',
        'upcoming' => 'الاجتماعات القادمة',
        'past' => 'الاجتماعات السابقة',
        'empty' => 'لا اجتماعات قادمة.',
        'past_empty' => 'لا اجتماعات سابقة.',
        'details' => 'عرض التفاصيل',
        'back' => 'كل الاجتماعات',
        'when' => 'الموعد',
        'where' => 'المكان',
        'online' => 'عن بعد عبر مايكروسوفت تيمز',
        'join' => 'الانضمام عبر تيمز',
        'link_pending' => 'يظهر رابط الانضمام هنا عند تجهيزه.',
        'agenda' => 'جدول الأعمال',
        'attachments' => 'المرفقات',
        'attendance' => 'الحضور',
        'attendance_count' => ':present من :total عضواً',
        'attendance_only' => 'حضر :present عضواً',
        'quorum_met' => 'اكتمل النصاب',
        'quorum_not_met' => 'لم يكتمل النصاب',
        'minutes' => 'المحضر',
        'minutes_download' => 'تنزيل المحضر المعتمد',
        'minutes_pending' => 'يظهر المحضر هنا بعد اعتماده.',
        'cancelled_notice' => 'أُلغي هذا الاجتماع.',
    ],

    'minutes' => [
        'title' => 'المحاضر المعتمدة',
        'empty' => 'لا محاضر معتمدة بعد.',
        'download' => 'تنزيل المحضر',
        'approved_on' => 'اعتُمد في :date',
    ],

    'files' => [
        'title' => 'ملفات الأعضاء',
        'empty' => 'لا ملفات بعد.',
        'all' => 'كل التصنيفات',
        'download' => 'تنزيل',
        'pdf' => 'PDF',
        'board_only' => 'لمجلس الإدارة',
    ],

    'membership_statuses' => [
        'active' => 'فعّالة',
        'suspended' => 'موقوفة',
        'ended' => 'منتهية',
    ],

    'meeting_types' => [
        'ordinary' => 'عادي',
        'extraordinary' => 'غير عادي',
    ],

    'meeting_statuses' => [
        'scheduled' => 'مجدول',
        'held' => 'منعقد',
        'cancelled' => 'ملغى',
    ],

    'minutes_statuses' => [
        'none' => 'لم يُرفع',
        'draft' => 'مسودة',
        'approved' => 'معتمد',
    ],

    'file_categories' => [
        'financial_statements' => 'القوائم المالية',
        'reports' => 'التقارير',
        'decisions' => 'القرارات',
    ],

    'file_audiences' => [
        'members' => 'أعضاء الجمعية العمومية',
        'board' => 'مجلس الإدارة وحده',
    ],

    // لوحة الإدارة
    'memberships' => [
        'model' => 'عضوية',
        'plural' => 'سجل الأعضاء',
    ],

    'meeting' => [
        'model' => 'اجتماع',
        'plural' => 'الاجتماعات',
    ],

    'file' => [
        'model' => 'ملف للأعضاء',
        'plural' => 'ملفات الأعضاء',
    ],

    'fields' => [
        'user' => 'العضو',
        'status' => 'الحالة',
        'joined_on' => 'تاريخ العضوية',
        'service_request' => 'طلب الانضمام',
        'title' => 'العنوان',
        'type' => 'النوع',
        'starts_at' => 'موعد البدء',
        'ends_at' => 'موعد الانتهاء',
        'ends_at_hint' => 'إلزامي للاجتماع عن بعد.',
        'location' => 'المكان',
        'is_online' => 'عن بعد عبر مايكروسوفت تيمز',
        'agenda' => 'جدول الأعمال',
        'attachments' => 'المرفقات (PDF)',
        'quorum_percent' => 'نسبة النصاب المطلوبة (٪)',
        'quorum_percent_hint' => 'من اللائحة الأساسية للجمعية. وبلا نسبة يُعرض عدد الحاضرين وحده.',
        'online_link' => 'رابط تيمز',
        'minutes' => 'المحضر',
        'minutes_file' => 'ملف المحضر (PDF)',
        'category' => 'التصنيف',
        'audience' => 'الاطلاع',
        'file' => 'الملف (PDF)',
        'attendees' => 'الحاضرون',
        'quorum' => 'النصاب',
        'created_at' => 'أُضيف في',
    ],

    'actions' => [
        'record_attendance' => 'تسجيل الحضور',
        'mark_held' => 'تسجيل الانعقاد',
        'cancel' => 'إلغاء الاجتماع',
        'cancel_confirm' => 'يُبلَّغ الأعضاء بالإلغاء، ويُلغى اجتماع تيمز إن وُجد. ولا رجوع عنه.',
        'upload_minutes' => 'رفع المحضر',
        'approve_minutes' => 'اعتماد المحضر',
        'approve_minutes_confirm' => 'بعد الاعتماد يظهر المحضر للأعضاء، ولا يُستبدل ولا يُحذف.',
        'download_minutes' => 'تنزيل المحضر',
        'download_attachment' => 'تنزيل: :name',
        'download' => 'تنزيل الملف',
        'retry_online' => 'إعادة إنشاء رابط تيمز',
    ],

    'notices' => [
        'attendance_recorded' => 'سُجّل الحضور.',
        'held' => 'سُجّل انعقاد الاجتماع.',
        'cancelled' => 'أُلغي الاجتماع، ويُبلَّغ الأعضاء.',
        'minutes_uploaded' => 'رُفع المحضر مسودةً، ويظهر للأعضاء بعد اعتماده.',
        'minutes_approved' => 'اعتُمد المحضر.',
        'online_retry' => 'يُعاد إنشاء الرابط في الخلفية.',
        'online_pending' => 'لم يُنشأ رابط تيمز بعد.',
    ],

    'validation' => [
        'place_or_online' => 'حدد المكان، أو اجعل الاجتماع عن بعد.',
        'online_unavailable' => 'ربط تيمز غير مضبوط بعد، فلا يُنشأ اجتماع عن بعد.',
        'already_member' => 'لهذا الحساب عضوية مسجلة.',
        'inactive_user' => 'لا تُمنح عضوية لحساب موقوف أو أُخفيت بياناته.',
        'not_active_member' => 'من اخترتهم ليسوا كلهم أعضاء فعّالين.',
    ],

    'mail' => [
        'scheduled_subject' => 'دعوة إلى اجتماع الجمعية العمومية: :title',
        'updated_subject' => 'تعديل اجتماع الجمعية العمومية: :title',
        'cancelled_subject' => 'إلغاء اجتماع الجمعية العمومية: :title',
        'scheduled_line' => 'تدعوكم الجمعية إلى اجتماع :type بعنوان «:title».',
        'updated_line' => 'عُدّل موعد اجتماع :type «:title» أو مكانه.',
        'cancelled_line' => 'أُلغي اجتماع :type «:title».',
        'when' => 'الموعد: :when',
        'where' => 'المكان: :where',
        'online' => 'عن بعد عبر مايكروسوفت تيمز، ورابط الانضمام في صفحة الاجتماع بالبوابة بعد الدخول.',
        'action' => 'صفحة الاجتماع',
    ],

];
