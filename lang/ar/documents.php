<?php

return [

    'templates' => [
        'model' => 'نموذج إلكتروني',
        'plural' => 'النماذج الإلكترونية',
    ],

    'entries' => [
        'model' => 'نموذج معبأ',
        'plural' => 'النماذج المعبأة',
    ],

    'categories' => [
        'model' => 'تصنيف أرشيف',
        'plural' => 'تصنيفات الأرشيف',
    ],

    'items' => [
        'model' => 'عنصر أرشيف',
        'plural' => 'الأرشيف والشواهد',
    ],

    'field_types' => [
        'text' => 'نص قصير',
        'long_text' => 'نص طويل',
        'number' => 'رقم',
        'date' => 'تاريخ',
        'select' => 'اختيار',
        'file' => 'مرفق PDF',
        'signature' => 'توقيع',
        'table' => 'جدول بصفوف',
        'heading' => 'عنوان قسم',
        'paragraph' => 'نص ثابت',
    ],

    'audiences' => [
        'staff' => 'الموظفون',
        'participants' => 'المشاركون',
    ],

    'widths' => [
        'full' => 'العرض كله',
        'half' => 'نصف العرض',
    ],

    'entry_statuses' => [
        'draft' => 'مسودة',
        'approved' => 'معتمد',
    ],

    'archive_types' => [
        'document' => 'وثيقة',
        'incoming' => 'مراسلة واردة',
        'outgoing' => 'مراسلة صادرة',
        'evidence' => 'شاهد',
    ],

    'fields' => [
        'title' => 'العنوان',
        'description' => 'الوصف',
        'is_active' => 'متاح للتعبئة',
        'fields' => 'الحقول',
        'fields_hint' => 'تعديل الحقول ينشئ إصداراً جديداً، والمعبأ قبله يبقى على إصداره.',
        'field_label' => 'اسم الحقل',
        'field_key' => 'المعرّف',
        'field_key_hint' => 'حروف إنجليزية صغيرة وأرقام وشرطة سفلية، يبدأ بحرف.',
        'field_type' => 'النوع',
        'field_required' => 'إلزامي',
        'field_options' => 'الخيارات',
        'version' => 'الإصدار',
        'versions' => 'الإصدارات',
        'template' => 'النموذج',
        'reference' => 'الرقم المرجعي',
        'status' => 'الحالة',
        'created_by' => 'عبّأه',
        'approved_by' => 'اعتمده',
        'approved_at' => 'وقت الاعتماد',
        'created_at' => 'أُضيف في',
        'signature_clear' => 'مسح التوقيع',
        'name' => 'الاسم',
        'roles' => 'الأدوار التي تطّلع عليه',
        'roles_hint' => 'والمشرف العام يطّلع على كل التصنيفات.',
        'type' => 'النوع',
        'category' => 'التصنيف',
        'entity' => 'الجهة',
        'dated_on' => 'التاريخ',
        'year' => 'السنة',
        'program' => 'البرنامج',
        'registration' => 'رقم القيد',
        'registration_hint' => 'يُعطى تلقائياً للمراسلات عند الحفظ، تسلسلياً في نوعها وسنتها.',
        'files' => 'الملفات (PDF وصور)',
        'answers' => 'الإجابات',
        'audience' => 'يُحفظ المعبأ في ملفات',
        'audience_hint' => 'نموذج المشاركين يرى صاحبه المعتمد منه في لوحته. ولا يتغير بعد أول معبأ.',
        'person' => 'صاحب النموذج',
        'person_hint' => 'يُحفظ المعبأ في ملفه: موظف في نماذج الموظفين، ومشارك في نماذج المشاركين.',
        'field_width' => 'العرض',
        'field_columns' => 'أعمدة الجدول',
        'field_columns_hint' => 'من عمود إلى ستة، ويضيف المعبّئ الصفوف.',
        'field_text' => 'النص',
        'table_add_row' => 'إضافة صف',
    ],

    'actions' => [
        'fill' => 'تعبئة نموذج',
        'approve' => 'اعتماد',
        'approve_confirm' => 'بعد الاعتماد يُقفل المعبأ، فلا يُعدَّل ولا يُحذف.',
        'print' => 'طباعة PDF',
        'download' => 'تنزيل: :name',
        'print_view' => 'عرض الطباعة',
        'export' => 'تصدير المعبأ',
    ],

    'notices' => [
        'approved' => 'اعتُمد المعبأ.',
    ],

    'print' => [
        'temporary_letterhead' => 'ترويسة مؤقتة حتى تعتمد الجمعية ترويستها',
        'reference' => 'الرقم المرجعي',
        'version' => 'الإصدار :version',
        'approved' => 'اعتمده :name في :date',
        'filled_by' => 'عبّأه :name في :date',
        'draft' => 'مسودة',
        'missing_contact' => '【ناقص: بيانات التواصل في ترويسة الجمعية — تُضبط من إعدادات الموقع】',
        'person' => 'صاحب النموذج: :name',
        'page' => 'صفحة :page من :pages',
        'print_now' => 'طباعة',
        'back' => 'رجوع',
    ],

    'export' => [
        'signed' => 'موقّع',
    ],

    'account' => [
        'nav' => 'نماذجي',
        'title' => 'نماذجي',
        'empty' => 'لا نماذج معتمدة في ملفك بعد.',
        'open' => 'عرض وطباعة',
    ],

    'validation' => [
        'invalid_fields' => 'راجع الحقول: لكل حقل اسم ومعرّف صالح لا يتكرر ونوع، ولحقل الاختيار خياراته، وبين حقل وأربعين.',
        'invalid_signature' => 'التوقيع غير صالح، فأعد رسمه.',
        'invalid_file' => 'الملف ليس PDF ولا صورة صالحة.',
        'numbered_year' => 'رقم قيد المراسلة لسنتها، فلا تُنقل إلى سنة أخرى.',
        'invalid_person' => 'صاحب النموذج غير صالح: موظف في نماذج الموظفين، ومشارك في نماذج المشاركين.',
        'audience_locked' => 'لا يتغير جمهور النموذج بعد أول معبأ.',
    ],

];
