<?php

/*
| السجلات والمستندات ذات الصلاحية (docs/plans/registers.md).
| مدة التنبيه الافتراضية قبل الانتهاء بالأيام متى لم تُضبط من شاشة السجلات (إعداد registers).
| قيمة تقنية مؤقتة حتى تحدد الجمعية مدتها (docs/open-questions.md).
*/

return [

    'default_alert_days' => (int) env('REGISTERS_DEFAULT_ALERT_DAYS', 30),

    // مدة رابط تنزيل المرفق الموقّع بالدقائق
    'link_minutes' => 5,

];
