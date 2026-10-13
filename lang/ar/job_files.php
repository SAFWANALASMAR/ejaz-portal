<?php

return [

    'model' => 'ملف وظيفة',
    'plural' => 'ملفات الوظائف',

    'documents' => [
        'model' => 'مستند',
        'plural' => 'المستندات',
        'empty' => 'لا مستندات في هذا الملف بعد.',
    ],

    'fields' => [
        'title' => 'اسم الملف',
        'description' => 'الوصف',
        'responsible_role' => 'الوظيفة المسؤولة',
        'participant_roles' => 'الوظائف المشاركة',
        'participants_contribute' => 'يضيف المشاركون مستندات',
        'participants_contribute_hint' => 'إن لم يُفعَّل يطّلع المشاركون فقط. والمشارك يعدّل ويحذف ما أضافه هو.',
        'icon' => 'الأيقونة',
        'sort' => 'الترتيب',
        'from_matrix' => 'من مصفوفة الملفات',
        'no_participants' => 'لا مشاركين',
        'document_title' => 'عنوان المستند',
        'document_description' => 'وصف المستند',
        'file' => 'الملف (PDF أو صورة)',
        'original_name' => 'اسم الملف المرفوع',
        'created_by' => 'أضافه',
        'updated_at' => 'آخر تعديل',
    ],

    'actions' => [
        'participants' => 'المشاركون',
        'download' => 'تنزيل',
    ],

    'notifications' => [
        'participants_saved' => 'حُفظ المشاركون.',
    ],

    'validation' => [
        'invalid_file' => 'الملف يجب أن يكون PDF أو صورة (JPG أو PNG أو WebP) سليمة.',
    ],

    'icons' => [
        'folder' => 'مجلد',
        'document' => 'مستند',
        'archive' => 'أرشيف',
        'envelope' => 'مراسلات',
        'users' => 'أشخاص',
        'briefcase' => 'حقيبة',
        'chart' => 'مؤشرات',
        'banknotes' => 'مال',
        'calculator' => 'حاسبة',
        'scale' => 'ميزان',
        'shield' => 'درع',
        'megaphone' => 'إعلام',
        'photo' => 'صور',
        'calendar' => 'تقويم',
        'clipboard' => 'قائمة',
        'warning' => 'تنبيه',
        'idea' => 'فكرة',
    ],

];
