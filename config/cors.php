<?php

/*
| لا واجهة برمجية في البوابة، فلا مسار يرد رؤوس CORS. وإعداد لارافيل الافتراضي كان يرد
| Access-Control-Allow-Origin: * على كل /api/* (اختبار الاختراق الآلي، 20 أكتوبر 2026). ورفع المقاطع المباشر
| إلى التخزين بإعداد CORS الحاوية نفسها لا التطبيق (deploy/readiness.md §٨).
*/

return [

    'paths' => [],

    'allowed_methods' => [],

    'allowed_origins' => [],

    'allowed_origins_patterns' => [],

    'allowed_headers' => [],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
