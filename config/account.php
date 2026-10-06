<?php

/*
| قائمة لوحة المستخدم. كل وحدة تضيف رابطها هنا عند بنائها.
| 'can' اختياري: صلاحية مطلوبة لظهور الرابط.
*/

return [

    'navigation' => [
        ['route' => 'account.dashboard', 'label' => 'account.nav.dashboard'],
        ['route' => 'account.profile', 'label' => 'account.nav.profile'],
        ['route' => 'account.security', 'label' => 'account.nav.security'],
        ['route' => 'account.data', 'label' => 'account.nav.data'],
    ],

];
