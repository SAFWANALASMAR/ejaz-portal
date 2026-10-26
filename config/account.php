<?php

/*
| قائمة لوحة المستخدم (docs/design/ui-reference.md — لوحة المستخدم).
| كل وحدة تضيف عنصرها هنا عند بنائها، فلا يظهر رابط لوحدة لم تُبنَ.
|
| route:       المسار
| label:       مفتاح الترجمة للاسم
| description: مفتاح الترجمة لوصف البطاقة في الرئيسية (اختياري؛ بدونه لا تظهر بطاقة)
| icon:        اسم أيقونة Heroicons (outline)
| active:      أسماء المسارات التي تجعل العنصر نشطاً
| can:         صلاحية مطلوبة لظهوره (اختياري)
*/

return [

    'navigation' => [
        [
            'route' => 'account.dashboard',
            'label' => 'account.nav.dashboard',
            'icon' => 'home',
            'active' => ['account.dashboard'],
        ],
        [
            'route' => 'account.profile',
            'label' => 'account.nav.profile',
            'description' => 'account.nav.profile_description',
            'icon' => 'user',
            'active' => ['account.profile', 'account.security', 'account.data*', 'account.phone*'],
        ],
        [
            'route' => 'account.programs.index',
            'label' => 'programs.nav.programs',
            'description' => 'programs.nav.programs_description',
            'icon' => 'academic-cap',
            'active' => ['account.programs.*'],
        ],
        [
            'route' => 'account.requests.index',
            'label' => 'services.nav.requests',
            'description' => 'services.nav.requests_description',
            'icon' => 'inbox-stack',
            'active' => ['account.requests.*'],
        ],
        [
            // لمن يصل إلى منطقة الجمعية العمومية وحده (الصورة 04)
            'route' => 'assembly.index',
            'label' => 'assembly.nav',
            'description' => 'assembly.nav_description',
            'icon' => 'user-group',
            'active' => ['assembly.*'],
            'can' => 'accessAssemblyArea',
            // مؤجلة بقرار المدير التنفيذي (AccountSettings::showsDeferredPortals)
            'deferred' => true,
        ],
        [
            'route' => 'account.competitions',
            'label' => 'competitions.account.nav',
            'description' => 'competitions.account.nav_description',
            'icon' => 'trophy',
            'active' => ['account.competitions', 'competitions.entry'],
        ],
        [
            'route' => 'account.payments',
            // مؤرشفة خلف PAYMENTS_ENABLED
            'enabled' => 'payments.enabled',
            'label' => 'payments.account.nav',
            'description' => 'payments.account.nav_description',
            'icon' => 'banknotes',
            'active' => ['account.payments'],
        ],
        [
            // المعتمد من نماذج المشاركين المحفوظة في ملفه (docs/plans/forms-print.md)، لمن له منها شيء.
            // بلا بطاقة في الرئيسية
            'route' => 'account.forms.index',
            'label' => 'documents.account.nav',
            'icon' => 'document-text',
            'active' => ['account.forms.*'],
            'can' => 'viewOwnFormEntries',
        ],
    ],

    // بطاقات رئيسية لوحة المستفيد وترتيبها تُدار من اللوحة (App\Models\AccountCard — ملاحظات المدير التنفيذي، 25 أكتوبر 2026)

];
