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
    ],

    'actions' => [
        'fill' => 'تعبئة نموذج',
        'approve' => 'اعتماد',
        'approve_confirm' => 'بعد الاعتماد يُقفل المعبأ، فلا يُعدَّل ولا يُحذف.',
        'print' => 'طباعة PDF',
        'download' => 'تنزيل: :name',
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
    ],

    'validation' => [
        'invalid_fields' => 'راجع الحقول: لكل حقل اسم ومعرّف صالح لا يتكرر ونوع، ولحقل الاختيار خياراته، وبين حقل وأربعين.',
        'invalid_signature' => 'التوقيع غير صالح، فأعد رسمه.',
        'invalid_file' => 'الملف ليس PDF ولا صورة صالحة.',
        'numbered_year' => 'رقم قيد المراسلة لسنتها، فلا تُنقل إلى سنة أخرى.',
    ],

];
