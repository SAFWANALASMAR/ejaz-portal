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
        // «الخدمات الإلكترونية» تضم الدورات والبرامج والمسابقات (البند R4)، ثم الاستفسارات (Final-Services، النسخة 63). والمبادرات في «عن الجمعية»
        // لا هنا (ملاحظات المدير التنفيذي، 4 نوفمبر 2026 — MenuSections::about)
        ['route' => 'programs.index', 'label' => 'ui.inner.services.programs', 'icon' => 'academic-cap', 'symbol' => 'programs'],
        ['route' => 'competitions.index', 'label' => 'ui.inner.services.competitions', 'icon' => 'trophy', 'symbol' => 'competitions'],
        ['route' => 'contact', 'label' => 'ui.inner.services.inquiries', 'icon' => 'chat-bubble-left-right', 'symbol' => 'inquiries'],
        ['route' => 'services.assembly-membership', 'label' => 'services.tiles.membership', 'icon' => 'user-group', 'symbol' => 'membership'],
        ['route' => 'services.jobs', 'label' => 'services.tiles.jobs', 'icon' => 'briefcase', 'symbol' => 'jobs'],
        ['route' => 'services.volunteering', 'label' => 'services.tiles.volunteering', 'icon' => 'hand-raised', 'symbol' => 'volunteering'],
        // «قياس الرضا» ظاهر دائماً، فالبطاقات تسع في شبكة 3×3 بلا خانة فارغة (إضافة توجيه المدير، البند ١٧ — مراجعة المشرف على #157):
        // يفتح الاستبيان العام المفتوح، وبلا استبيان مفتوح صفحة «قياس الرضا» برسالة أن لا استبيان الآن (SurveyController::index)، لا 404
        ['route' => 'surveys.index', 'label' => 'services.tiles.surveys', 'icon' => 'face-smile', 'symbol' => 'surveys', 'wide' => true],
    ],

];
