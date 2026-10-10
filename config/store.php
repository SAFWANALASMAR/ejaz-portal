<?php

/*
| متجر الجمعية على سلة (قرار صفوان، 10 أكتوبر 2026 — docs/decisions.md). الرابط يُضبط من إعدادات الموقع،
| ولا يُقبل إلا https من نطاق مسموح. وهذا الرابط المعتمد حين لا يُضبط أو لا يصح.
*/

return [

    // كتالوج المتجر الداخلي المؤرشف (docs/plans/store-payments.md): settings يعرض بطاقات من إعدادات الموقع،
    // وواجهة سلة البرمجية تُضاف مشغّلاً حين تتوفر صلاحية الربط
    'driver' => env('STORE_DRIVER', 'settings'),

    'url' => env('STORE_URL', 'https://ejaz.org.sa'),

    // النطاقات المسموحة لرابط المتجر مطابقةً تامة، والفرعية بنمط صريح (*.ejaz.org.sa). مفصولة بفواصل في .env
    'allowed_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('STORE_ALLOWED_HOSTS', 'ejaz.org.sa'))))),

];
