<?php

/*
| السجلات والمستندات ذات الصلاحية، وسجلات المجالس واللجان، والخطة السنوية، وحسابات المنصات
| (requirements/13 — docs/plans/registers.md).
*/

return [

    'entry' => [
        'model' => 'مستند',
        'plural' => 'السجلات والمستندات',
    ],
    'committee' => [
        'model' => 'لجنة',
        'plural' => 'اللجان',
    ],
    'membership' => [
        'model' => 'عضوية',
        'plural' => 'أعضاء المجلس واللجان',
    ],
    'plan' => [
        'model' => 'بند في الخطة',
        'plural' => 'الخطة السنوية للبرامج',
        'unspecified' => 'غير محدد',
        'progress' => 'إنجاز الخطة',
        'widget_heading' => 'إنجاز الخطة السنوية :year',
        'executed_items' => 'البنود المنفذة',
        'executed_of' => ':executed من :items (:percent٪)',
        'beneficiaries' => 'المستفيدون الفعليون',
        'beneficiaries_of' => ':actual من :target مستهدف',
        'by_department' => 'حسب الإدارة',
        'by_type' => 'حسب النوع',
        'empty' => 'لا بنود في خطة هذه السنة.',
    ],
    'platform' => [
        'model' => 'حساب منصة',
        'plural' => 'حسابات المنصات',
        'no_passwords' => 'لا تُكتب كلمات المرور هنا. تُحفظ في مدير كلمات المرور المعتمد خارج البوابة.',
    ],
    'agenda' => [
        'model' => 'بند',
        'plural' => 'بنود جدول الأعمال وقراراتها',
    ],

    'types' => [
        'official_document' => 'مستند رسمي',
        'contract' => 'عقد',
        'license' => 'ترخيص',
        'partnership' => 'شراكة',
        'property' => 'ملك',
        'investment' => 'استثمار',
        'activity_approval' => 'موافقة نشاط',
        'permit' => 'تصريح',
    ],

    'statuses' => [
        'valid' => 'صالح',
        'expiring' => 'قارب على الانتهاء',
        'expired' => 'منتهٍ',
        'no_expiry' => 'بلا انتهاء',
    ],

    'bodies' => [
        'general_assembly' => 'الجمعية العمومية',
        'board' => 'مجلس الإدارة',
        'committee' => 'لجنة',
    ],

    'body_roles' => [
        'chair' => 'رئيس',
        'vice_chair' => 'نائب الرئيس',
        'secretary' => 'أمين السر',
        'member' => 'عضو',
    ],

    'agenda_statuses' => [
        'not_started' => 'لم يبدأ',
        'in_progress' => 'قيد التنفيذ',
        'completed' => 'منفّذ',
    ],

    'fields' => [
        'type' => 'النوع',
        'title' => 'العنوان',
        'issuer' => 'الجهة',
        'document_number' => 'رقم المستند',
        'issued_on' => 'تاريخ الإصدار',
        'expires_on' => 'تاريخ الانتهاء',
        'expires_on_hint' => 'آخر يوم صالح. اتركه فارغاً لما لا ينتهي.',
        'alert_days' => 'التنبيه قبل الانتهاء (أيام)',
        'default_alert_days' => 'مدة التنبيه الافتراضية (أيام)',
        'responsible' => 'المسؤول',
        'notes' => 'الملاحظات',
        'attachments' => 'المرفقات (PDF أو صور)',
        'remaining_days' => 'المتبقي (أيام)',
        'status' => 'الحالة',
        'created_at' => 'تاريخ الإضافة',
        'body' => 'الجهة',
        'committee' => 'اللجنة',
        'name' => 'الاسم',
        'description' => 'الوصف',
        'user' => 'العضو',
        'role' => 'الصفة',
        'starts_on' => 'من',
        'ends_on' => 'إلى',
        'active' => 'فعّالة',
        'position' => 'الترتيب',
        'item' => 'البند',
        'decision' => 'القرار',
        'assignee' => 'المكلَّف',
        'due_on' => 'الموعد',
        'execution_status' => 'حالة التنفيذ',
        'year' => 'السنة',
        'department' => 'الإدارة',
        'plan_type' => 'النوع',
        'activity' => 'البرنامج أو النشاط',
        'objective_breakdown' => 'تقسيم الهدف',
        'estimated_budget' => 'الموازنة التقديرية',
        'supporting_entities' => 'الجهات المساندة',
        'target_group' => 'الفئة المستهدفة',
        'target_beneficiaries' => 'عدد المستفيدين المستهدف',
        'preparation_period' => 'فترة الإعداد',
        'plan_starts_on' => 'التنفيذ من',
        'plan_ends_on' => 'التنفيذ إلى',
        'approvals' => 'الموافقات',
        'program' => 'البرنامج المنفذ',
        'executed' => 'التنفيذ',
        'actual_beneficiaries' => 'المستفيدون الفعليون',
        'platform' => 'الجهة أو المنصة',
        'username' => 'اسم المستخدم',
        'login_url' => 'رابط الدخول',
    ],

    'actions' => [
        'download' => 'تنزيل :name',
        'defaults' => 'مدة التنبيه الافتراضية',
        'defaults_saved' => 'حُفظت مدة التنبيه الافتراضية.',
    ],

    'widget' => [
        'heading' => 'المستندات ذات الصلاحية',
        'expired' => 'منتهية',
        'expiring' => 'قاربت على الانتهاء',
        'nearest' => 'الأقرب انتهاءً',
        'none' => 'لا مستندات منتهية أو قريبة من الانتهاء.',
        'days' => ':days يوم',
    ],

    'mail' => [
        'expiring_subject' => 'قارب على الانتهاء: :title',
        'expiring_line' => 'يقترب انتهاء :type «:title» في :date. يُرجى متابعة تجديده.',
        'expired_subject' => 'انتهى: :title',
        'expired_line' => 'انتهى :type «:title» في :date. يُرجى متابعة تجديده أو تحديث سجله.',
        'action' => 'عرض المستند',
    ],

    'validation' => [
        'responsible' => 'المسؤول يجب أن يكون حساباً فعّالاً يطّلع على السجلات.',
        'invalid_file' => 'المرفق يجب أن يكون ملف PDF أو صورة (JPG أو PNG أو WebP).',
    ],

];
