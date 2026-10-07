<?php

/*
| المتجر (docs/plans/store-payments.md): settings يعرض بطاقات من إعدادات الموقع، وواجهة سلة
| البرمجية تُضاف مشغّلاً حين تتوفر صلاحية الربط (docs/open-questions.md).
*/

return [

    'driver' => env('STORE_DRIVER', 'settings'),

];
