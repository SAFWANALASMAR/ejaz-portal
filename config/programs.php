<?php

/*
| البرامج والدورات (docs/requirements/03-programs-courses.md).
*/

return [

    // شهادة الحضور لا تُصدر في الإنتاج حتى يُستبدل القالب المؤقت بقالب الجمعية المعتمد، ثم يُضبط هذا المفتاح
    // (decisions.md — 19 أكتوبر 2026). وفي التطوير والتجريبية تُصدر بالقالب المؤقت للتجربة
    'certificate_template_ready' => (bool) env('PROGRAM_CERTIFICATE_TEMPLATE_READY', false),

];
