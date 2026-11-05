<?php

/*
| متجر الجمعية على سلة (قرار صفوان، 10 أكتوبر 2026 — docs/decisions.md). الرابط يُضبط من إعدادات الموقع،
| ولا يُقبل إلا https من نطاق مسموح. وهذا الرابط المعتمد حين لا يُضبط أو لا يصح.
*/

return [

    // كتالوج صفحة «المتجر والتبرعات» (docs/decisions/2026-11-04-store-page.md): manual يعرض العناصر المدخلة من اللوحة،
    // وsettings بطاقات إعدادات الموقع للمتجر الداخلي المؤرشف (docs/plans/store-payments.md). وواجهة Checkout في سلة تُضاف
    // مشغّلاً حين يُقرر الربط الآلي
    'driver' => env('STORE_DRIVER', 'manual'),

    'url' => env('STORE_URL', 'https://ejaz.org.sa'),

    // النطاقات المسموحة لرابط المتجر مطابقةً تامة، والفرعية بنمط صريح (*.ejaz.org.sa). مفصولة بفواصل في .env
    'allowed_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('STORE_ALLOWED_HOSTS', 'ejaz.org.sa'))))),

];
