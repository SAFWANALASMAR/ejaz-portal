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
        'redirects' => $crud,

        // الطلبات والتواصل
        'requests' => ['view', 'update', 'assign', 'export', 'delete'],
        'openings' => $crud,

        // البرامج والدورات
        'program_types' => $crud,
        'programs' => [...$crud, 'export'],

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
    ],

    // الأدوار وصلاحياتها. '*' تعني كل عمليات الوحدة.
    'roles' => [
        'board_member' => [
            'menu.view', 'pages.view', 'initiatives.view', 'board.view', 'governance.view',
            'library.view', 'news.view', 'media.view', 'partners.view',
            'competitions.view',
            'surveys.view', 'stats.view',
        ],
        'secretary' => [
            'requests.*',
            'openings.*',
        ],
        'content_editor' => [
            'menu.*', 'settings.*', 'pages.*', 'initiatives.*', 'board.*', 'governance.*',
            'library.*', 'news.*', 'media.*', 'partners.*', 'redirects.*',
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
    ],

];
