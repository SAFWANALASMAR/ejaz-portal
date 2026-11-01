<?php

use App\Models\Survey;

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
        // «الخدمات الإلكترونية» تضم الدورات والبرامج والمسابقات والمبادرات (البند R4)، ثم الاستفسارات (Final-Services، النسخة 63)
        ['route' => 'programs.index', 'label' => 'ui.inner.services.programs', 'icon' => 'academic-cap'],
        ['route' => 'competitions.index', 'label' => 'ui.inner.services.competitions', 'icon' => 'trophy'],
        ['route' => 'initiatives.index', 'label' => 'ui.inner.services.initiatives', 'icon' => 'flag'],
        ['route' => 'contact', 'label' => 'ui.inner.services.inquiries', 'icon' => 'chat-bubble-left-right'],
        ['route' => 'services.assembly-membership', 'label' => 'services.tiles.membership', 'icon' => 'user-group'],
        ['route' => 'services.jobs', 'label' => 'services.tiles.jobs', 'icon' => 'briefcase'],
        ['route' => 'services.volunteering', 'label' => 'services.tiles.volunteering', 'icon' => 'hand-raised'],
        // الاستبيان العام المفتوح، متى وُجد
        ['route' => 'surveys.index', 'label' => 'services.tiles.surveys', 'icon' => 'face-smile', 'wide' => true, 'available' => [Survey::class, 'hasOpenGeneral']],
    ],

];
