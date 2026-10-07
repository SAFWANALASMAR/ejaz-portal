<?php

/*
| بطاقات صفحة «خدماتنا الإلكترونية» (docs/design/ui-reference.md — الصورة 06 اللوحي).
| البطاقة لا تظهر إلا إن وُجد مسارها، فكل وحدة تضيف بطاقتها هنا عند بنائها:
| الاستفسارات والاقتراحات (بند التواصل)، وخدمات الباحثين، وقياس الرضا (بعرض كامل).
|
| route: المسار — label: مفتاح الترجمة — icon: أيقونة Heroicons (outline) — wide: بعرض كامل
*/

return [

    'tiles' => [
        ['route' => 'services.assembly-membership', 'label' => 'services.tiles.membership', 'icon' => 'user-group'],
        ['route' => 'services.jobs', 'label' => 'services.tiles.jobs', 'icon' => 'briefcase'],
        ['route' => 'services.volunteering', 'label' => 'services.tiles.volunteering', 'icon' => 'hand-raised'],
    ],

];
