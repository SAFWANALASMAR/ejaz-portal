<?php

return [

    'model' => 'عملية',
    'plural' => 'سجل العمليات',

    'fields' => [
        'created_at' => 'الوقت',
        'log_name' => 'السجل',
        'event' => 'الحدث',
        'causer' => 'المنفّذ',
        'subject' => 'السجل المتأثر',
        'old' => 'القيم السابقة',
        'new' => 'القيم الجديدة',
        'properties' => 'تفاصيل',
        'system' => 'النظام',
    ],

    'logs' => [
        'default' => 'التعديلات',
        'security' => 'الأمن',
        'users' => 'المستخدمون',
        'content' => 'المحتوى',
    ],

];
