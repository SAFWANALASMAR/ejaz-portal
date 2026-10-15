<?php

/*
| الصلاحيات التفصيلية — docs/roles-permissions.md
|
| الصلاحية = <الوحدة>.<العملية>، مثل news.update. تُنشأ وتُربط بالأدوار بالأمر:
|   php artisan db:seed --class=PermissionsSeeder
| والمشرف العام يملك دون ربط كل ما لم تمنعه سياسة صراحة (Gate::after في AppServiceProvider).
| كل وحدة جديدة تضيف عملياتها هنا وتربطها بأدوارها.
*/

$crud = ['view', 'create', 'update', 'delete'];

return [

    'modules' => [
        // المحتوى العام
        'menu' => $crud,
        'settings' => ['update'],
        'pages' => $crud,
        'initiatives' => $crud,
        'board' => $crud,
        'governance' => $crud,
        'library' => $crud,
        'news' => $crud,
        'media' => $crud,
        'partners' => $crud,
        'livestreams' => $crud,
        'redirects' => $crud,
        // مكتبة الوسائط: الإضافة بأمر الاستيراد، ولا حذف (MediaAssetPolicy)
        'media_assets' => ['view', 'create', 'update'],

        // الطلبات والتواصل
        'requests' => ['view', 'update', 'assign', 'export', 'delete'],
        'openings' => $crud,

        // البرامج والدورات
        'program_types' => $crud,
        // attend: تسجيل حضور المسجلين دون تعديل البرنامج (فئة الموظفين — requirements/12 §12)
        'programs' => [...$crud, 'export', 'attend'],

        // الإدارة
        // منح الأدوار ليس صلاحية تُمنح: للمشرف العام وحده (UserPolicy::assignRoles)
        'users' => $crud,
        'activity' => ['view'],
        // مدة الاحتفاظ ببيانات الحسابات المحذوفة: لا تُربط بدور، فهي للمشرف العام وحده (Gate::after)
        'retention' => ['update'],

        // المسابقات (المرحلة 2)
        'competitions' => ['view', 'create', 'update', 'delete', 'approve', 'draw'],
        // المشاركات منفصلة لأن فيها بيانات المتقدمين الشخصية
        'submissions' => ['view', 'assign', 'export', 'delete'],
        // التحكيم: السياسة تحصره في المسند إلى المحكّم نفسه
        'reviews' => ['view', 'update'],

        // قياس الرضا والإحصائيات
        // تصدير النتائج منفصل عن الاطلاع: الملف يخرج من اللوحة (لمسؤول البرامج وحده)
        'surveys' => [...$crud, 'export'],
        'stats' => ['view'],

        // المدفوعات (المرحلة 5): سجل مالي للقراءة، والاسترداد للمشرف العام (بلا ربط)
        'payments' => ['view', 'view_donor', 'refund'],

        // الجمعية العمومية (المرحلة 4)
        // السجل يمنح دور عضو الجمعية العمومية، فالإنشاء والتعديل لا يُربطان بدور (المشرف العام وحده، قرار صفوان)
        'assembly_members' => ['view', 'create', 'update'],
        // view_all: كل الجهات (الجمعية العمومية والمجلس واللجان)، وبدونها يرى عضو المجلس الجمعية والمجلس ولجانه وحدها
        'meetings' => [...$crud, 'view_all'],
        // المحضر يرفعه السكرتير ويعتمده المجلس، فالعمليتان منفصلتان
        'minutes' => ['create', 'approve'],
        'assembly_files' => $crud,

        // النماذج الإلكترونية والأرشيف والشواهد (المرحلة 4)
        'forms' => $crud,
        // المعبأ يعبّئه السكرتير ويعتمده المجلس، وتصديره منفصل (بيانات قد تكون شخصية تخرج من اللوحة).
        // view_all: كل المعبأ، وبدونها يطّلع الحساب على ما عبّأه وحده (قرار صفوان، 14 أكتوبر 2026)
        'form_entries' => [...$crud, 'approve', 'export', 'view_all'],
        'archive' => $crud,
        // التصنيفات تحدد من يطّلع على ماذا، فلا تُربط بدور (المشرف العام وحده)
        'archive_categories' => $crud,

        // السجلات والمستندات ذات الصلاحية، واللجان وعضويتها، والخطة السنوية، وحسابات المنصات بلا كلمات مرور (requirements/13)
        'registers' => $crud,
        'committees' => $crud,
        'annual_plan' => $crud,
        'platform_accounts' => $crud,
        // ملفات الوظائف (requirements/11): الاطلاع على كل الملفات وإدارة سجلاتها. وما سواه بالسياسة: المسؤول
        // عن الملف والمشاركون فيه بأدوارهم
        'job_files' => $crud,
    ],

    // الأدوار وصلاحياتها. '*' تعني كل عمليات الوحدة.
    'roles' => [
        'board_member' => [
            'menu.view', 'pages.view', 'initiatives.view', 'board.view', 'governance.view',
            'library.view', 'news.view', 'media.view', 'partners.view',
            'livestreams.view',
            'competitions.view',
            'surveys.view', 'stats.view',
            'payments.view',
            'assembly_members.view', 'meetings.view', 'minutes.approve', 'assembly_files.view',
            // view_all: يعتمد المعبأ ولا يعبّئه، فيطّلع على ما عبّأه غيره
            'forms.view', 'form_entries.view', 'form_entries.approve', 'form_entries.view_all', 'archive.view',
            'registers.view', 'committees.view', 'annual_plan.view',
        ],
        'secretary' => [
            'requests.*',
            'openings.*',
            'assembly_members.view', 'meetings.*', 'minutes.create', 'assembly_files.*',
            'forms.*', 'form_entries.view', 'form_entries.create', 'form_entries.update', 'form_entries.delete', 'form_entries.export', 'archive.*',
            'registers.*', 'committees.*', 'platform_accounts.*',
            // من فئة الموظفين: «متابعة البرامج» و«تسجيل الحضور»
            'programs.view', 'programs.attend',
        ],
        'content_editor' => [
            'menu.*', 'settings.*', 'pages.*', 'initiatives.*', 'board.*', 'governance.*',
            'library.*', 'news.*', 'media.*', 'partners.*', 'redirects.*', 'media_assets.*',
            'livestreams.*',
        ],
        'program_manager' => [
            'program_types.*', 'programs.*',
            'competitions.*', 'submissions.*',
            'surveys.*',
        ],
        'reviewer' => [
            'reviews.*',
        ],
        'assembly_member' => [],
        'member' => [],

        // بوابات الدخول الخمس (requirements/12 §12، docs/plans/five-portals.md)
        // ب. المدير التنفيذي: ما للموظفين، و«إدارة الموظفين» (المستخدمون محصورون في حسابات الموظفين،
        // والأدوار التي يمنحها في RoleName::assignableBy)، و«إدارة البرامج»، و«إدارة المسابقات»،
        // و«إدارة الملفات» (الأرشيف؛ وملفات المجلس للمجلس — open-questions)، و«الإحصائيات العامة»
        'executive_director' => [
            'job_files.*',
            'users.view', 'users.create', 'users.update',
            'program_types.*', 'programs.*',
            'competitions.*', 'submissions.*',
            'archive.*',
            'stats.view', 'surveys.view',
            // view_all: «إدارة الموظفين»، فيطّلع على ما عبّأه الموظفون كما يطّلع على كل ملفات الوظائف
            'forms.*', 'form_entries.view', 'form_entries.create', 'form_entries.update', 'form_entries.delete', 'form_entries.view_all',
            // السجلات والخطة السنوية (requirements/13)
            'registers.*', 'committees.view', 'meetings.view', 'meetings.view_all', 'annual_plan.*', 'platform_accounts.*',
        ],
        // ج. الموظفون: «رفع الملفات» (وفوقها أدوار التصنيف)، و«تسجيل الحضور»، و«إدارة النماذج»، و«متابعة البرامج»
        'financial_manager' => [
            'archive.view', 'archive.create',
            'programs.view', 'programs.attend',
            'forms.*', 'form_entries.view', 'form_entries.create', 'form_entries.update', 'form_entries.delete',
        ],
        'accountant' => [
            'archive.view', 'archive.create',
            'programs.view', 'programs.attend',
            'forms.*', 'form_entries.view', 'form_entries.create', 'form_entries.update', 'form_entries.delete',
        ],
        'projects_manager' => [
            'archive.view', 'archive.create',
            'programs.view', 'programs.attend',
            'forms.*', 'form_entries.view', 'form_entries.create', 'form_entries.update', 'form_entries.delete',
            'annual_plan.*',
        ],
        'marketing_manager' => [
            'archive.view', 'archive.create',
            'programs.view', 'programs.attend',
            'forms.*', 'form_entries.view', 'form_entries.create', 'form_entries.update', 'form_entries.delete',
        ],
    ],

];
