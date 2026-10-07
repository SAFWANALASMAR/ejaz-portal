<?php

return [

    'model' => 'عملية',
    'plural' => 'سجل العمليات',

    'fields' => [
        'created_at' => 'الوقت',
        'log_name' => 'السجل',
        'event' => 'الحدث',
        'causer' => 'المنفّذ',
        'subject' => 'السجل المتأثر',
        'old' => 'القيم السابقة',
        'new' => 'القيم الجديدة',
        'properties' => 'تفاصيل',
        'system' => 'النظام',
        'unknown' => 'غير معروف',
    ],

    'logs' => [
        'default' => 'التعديلات',
        'security' => 'الأمن',
        'users' => 'المستخدمون',
        'content' => 'المحتوى',
        'competitions' => 'المسابقات',
        'surveys' => 'قياس الرضا',
    ],

    'events' => [
        'created' => 'إنشاء',
        'updated' => 'تعديل',
        'deleted' => 'حذف',
        'restored' => 'استعادة',
        'login' => 'دخول',
        'login_failed' => 'محاولة دخول فاشلة',
        'login_lockout' => 'قفل الدخول بعد محاولات خاطئة',
        'logout' => 'خروج',
        'password_reset' => 'تعيين كلمة المرور',
        'registered' => 'تسجيل حساب جديد',
        'admin_created' => 'إنشاء مشرف عام من سطر الأوامر',
        'data_exported' => 'تنزيل نسخة من البيانات الشخصية',
        'invitation_sent' => 'إرسال دعوة تعيين كلمة المرور',
        'roles_changed' => 'تغيير الأدوار',
        'personal_data_changed' => 'تعديل البيانات الشخصية',
        'anonymized' => 'إخفاء البيانات بطلب الحذف',
        'menu_reordered' => 'إعادة ترتيب القائمة',
        'content_reordered' => 'إعادة ترتيب المحتوى',
        'results_approved' => 'اعتماد نتائج مسابقة',
        'draw_executed' => 'تنفيذ سحب إلكتروني',
        'draw_voided' => 'إلغاء سحب إلكتروني',
        'submissions_distributed' => 'توزيع آلي للمشاركات على المحكّمين',
        'submissions_exported' => 'تصدير مشاركات مسابقة',
        'survey_results_exported' => 'تصدير نتائج استبيان',
        'personal_data_purged' => 'حذف بيانات حساب محذوف بعد مدة الاحتفاظ',
    ],

    // نوع السجل المتأثر (اسم الصنف)
    'subjects' => [
        'User' => 'حساب',
        'MenuItem' => 'عنصر قائمة',
        'Setting' => 'إعدادات الموقع',
        'Page' => 'صفحة',
        'Initiative' => 'مبادرة',
        'BoardMember' => 'عضو مجلس الإدارة',
        'Document' => 'وثيقة',
        'Competition' => 'مسابقة',
        'CompetitionQuestion' => 'سؤال مسابقة',
        'CompetitionCriterion' => 'معيار تقييم',
        'CompetitionSubmission' => 'مشاركة في مسابقة',
        'SubmissionReview' => 'تحكيم مشاركة',
        'CompetitionDraw' => 'سحب إلكتروني',
        'CompetitionDrawVoid' => 'إلغاء سحب',
        'Survey' => 'استبيان',
        'SurveyQuestion' => 'سؤال استبيان',
    ],

];
