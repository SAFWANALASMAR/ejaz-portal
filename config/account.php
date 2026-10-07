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
            'route' => 'account.requests.index',
            'label' => 'services.nav.requests',
            'description' => 'services.nav.requests_description',
            'icon' => 'inbox-stack',
            'active' => ['account.requests.*'],
        ],
    ],

];
