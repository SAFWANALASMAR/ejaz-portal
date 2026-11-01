<?php

return [

    'title' => 'الجمعية العمومية',

    // عنصر لوحة المستخدم وبطاقته (الصورة 04)
    'nav' => 'الجمعية العمومية',
    'nav_description' => 'اجتماعات الجمعية العمومية ومحاضرها المعتمدة وملفات الأعضاء.',

    // بوابة مجلس الإدارة والجمعية العمومية لمن يصل إلى المنطقة (Final-Board — المرحلة د من #111)
    'portal' => [
        'eyebrow' => 'مجلس الإدارة والجمعية العمومية',
        'welcome' => 'مرحباً :name',
        'next' => 'اجتماعك القادم: :date',
        'intro' => 'كل ما تحتاجه للاجتماع والقرار في مكان واحد.',
        'join_next' => 'انضم للاجتماع القادم عبر تيمز',
        'join' => 'انضم عبر تيمز',
        'details' => 'جدول الأعمال',
        'stats' => 'إحصائيات المجلس',
        'members' => 'عضو في الجمعية العمومية',
        'meetings_this_year' => 'اجتماع هذا العام',
        'approved_minutes' => 'محضر معتمد',
        'open_assignments' => 'تكليف قيد التنفيذ',
        'shortcuts' => 'اختصارات',
        'upcoming' => 'الاجتماعات القادمة',
        'all_meetings' => 'كل الاجتماعات',
        'upcoming_empty' => 'لا اجتماعات قادمة.',
        'records' => 'المحاضر والقرارات',
        'records_all' => 'السجل',
        'minutes_of' => 'محضر: :title',
        'minutes_approved' => 'معتمد',
        'records_empty' => 'لا محاضر معتمدة ولا قرارات بعد.',
        'items' => '{0} بلا بنود|{1} بند واحد|{2} بندان|[3,10] :count بنود|[11,*] :count بنداً',
        'remote' => 'عن بُعد عبر تيمز',
    ],

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
        'online_sync' => 'ربط تيمز',
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
        'retry_online' => 'إعادة محاولة ربط تيمز',
    ],

    'filters' => [
        'online_failed' => 'تعذّر ربطه بتيمز',
    ],

    'online_statuses' => [
        'linked' => 'مرتبط',
        'pending' => 'قيد المزامنة',
        'failed' => 'متعذر',
    ],

    // سبب تعذر الربط على الاجتماع وفي «اختبار الاتصال» (OnlineMeetingFailure)، وما يفعله المسؤول
    'online_failures' => [
        'not_configured' => 'ربط تيمز غير مضبوط',
        'invalid_credentials' => 'رفضت Microsoft بيانات التطبيق',
        'access_denied' => 'التطبيق بلا صلاحية على تقويم المنظِّم',
        'organizer_not_found' => 'حساب المنظِّم غير موجود أو بلا صندوق بريد',
        'event_not_found' => 'حُذف الاجتماع من تقويم المنظِّم',
        'invalid_request' => 'رفضت Microsoft بيانات الاجتماع',
        'throttled' => 'Microsoft تحدّ الطلبات مؤقتاً',
        'timeout' => 'لم تستجب Microsoft في المهلة',
        'service_error' => 'عطل مؤقت لدى Microsoft',
        'unexpected' => 'رد غير متوقع من Microsoft',
    ],

    'online_failure_hints' => [
        'not_configured' => 'يفعّله المشرف العام ويكمل بياناته من «ربط تيمز» في الإعدادات، ثم أعد المحاولة.',
        'invalid_credentials' => 'تحقق من معرّف المستأجر ومعرّف التطبيق، وأن السر قيمته لا معرّفه ولم تنته مدته، من «ربط تيمز».',
        'access_denied' => 'امنح التطبيق صلاحية Calendars.ReadWrite (Application) بموافقة المدير، وتأكد أن صندوق المنظِّم داخل نطاق التطبيق في Exchange.',
        'organizer_not_found' => 'تحقق من بريد المنظِّم، وأن له ترخيصاً فيه Exchange Online وتيمز.',
        'event_not_found' => 'أعد المحاولة فيُنشأ اجتماع جديد برابط جديد.',
        'invalid_request' => 'لا يُعاد آلياً. راجع عنوان الاجتماع وموعده ثم أعد المحاولة، وإن تكرر فراجع سجل التطبيق.',
        'throttled' => 'يُعاد تلقائياً خلال ست ساعات، أو أعد المحاولة لاحقاً.',
        'timeout' => 'يُعاد تلقائياً خلال ست ساعات، أو أعد المحاولة لاحقاً.',
        'service_error' => 'يُعاد تلقائياً خلال ست ساعات، أو أعد المحاولة لاحقاً.',
        'unexpected' => 'يُعاد تلقائياً خلال ست ساعات. وإن تكرر فراجع سجل التطبيق.',
    ],

    'notices' => [
        'attendance_recorded' => 'سُجّل الحضور.',
        'held' => 'سُجّل انعقاد الاجتماع.',
        'cancelled' => 'أُلغي الاجتماع، ويُبلَّغ الأعضاء.',
        'minutes_uploaded' => 'رُفع المحضر مسودةً، ويظهر للأعضاء بعد اعتماده.',
        'minutes_approved' => 'اعتُمد المحضر.',
        'online_retry' => 'تُعاد المزامنة مع تيمز في الخلفية.',
        'online_pending' => 'لم يُنشأ رابط تيمز بعد.',
    ],

    'validation' => [
        'place_or_online' => 'حدد المكان، أو اجعل الاجتماع عن بعد.',
        'online_in_sync' => 'الاجتماع مطابق لتيمز، فلا حاجة لإعادة المحاولة.',
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
