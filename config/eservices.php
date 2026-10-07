<?php

/*
| بطاقات صفحة «خدماتنا الإلكترونية» (docs/design/ui-reference.md — الصورة 06 اللوحي).
| البطاقة لا تظهر إلا إن وُجد مسارها، فكل وحدة تضيف بطاقتها هنا عند بنائها:
| الاستفسارات والاقتراحات (بند التواصل)، وخدمات الباحثين، وقياس الرضا (بعرض كامل).
|
| route: المسار — label: مفتاح الترجمة — icon: أيقونة Heroicons (outline) — wide: بعرض كامل —
| available: [صنف، دالة ثابتة] تقرر ظهورها، فلا تُعرض رابطاً إلى صفحة غير موجودة الآن
*/

return [

    'tiles' => [
        ['route' => 'services.assembly-membership', 'label' => 'services.tiles.membership', 'icon' => 'user-group'],
        ['route' => 'services.jobs', 'label' => 'services.tiles.jobs', 'icon' => 'briefcase'],
        ['route' => 'services.volunteering', 'label' => 'services.tiles.volunteering', 'icon' => 'hand-raised'],
        // الاستبيان العام المفتوح، متى وُجد
        ['route' => 'surveys.index', 'label' => 'services.tiles.surveys', 'icon' => 'face-smile', 'wide' => true, 'available' => [App\Models\Survey::class, 'hasOpenGeneral']],
    ],

];
