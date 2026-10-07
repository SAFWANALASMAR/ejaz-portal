<?php

/*
| الدفع (docs/plans/store-payments.md). المشغّل fake في التطوير والاختبارات، وmoyasar في الإنتاج
| بمفاتيح من .env وحده. والحدود هنا سقوف تقنية للنظام لا حدود الجمعية؛ والمبالغ المقترحة من اللوحة.
*/

return [

    // fake أو moyasar
    'driver' => env('PAYMENT_DRIVER', 'fake'),

    'currency' => 'SAR',

    // مهلة الاتصال بواجهة البوابة بالثواني
    'timeout_seconds' => 15,

    // الرمز السري لإشعارات البوابة (Secret Token في لوحة ميسر)، يُقارن بإشعارها. بلا رمز لا يُقبل إشعار
    'webhook_secret' => env('PAYMENT_WEBHOOK_SECRET', ''),

    'moyasar' => [
        'secret_key' => env('MOYASAR_SECRET_KEY', ''),
        'publishable_key' => env('MOYASAR_PUBLISHABLE_KEY', ''),
    ],

    'donation' => [
        // حدا التبرع بالهللات: الأدنى ريال واحد (أدنى ما تقبله البوابة)، والأقصى سقف تقني
        'min_halalas' => (int) env('DONATION_MIN_SAR', 1) * 100,
        'max_halalas' => (int) env('DONATION_MAX_SAR', 100000) * 100,
    ],

];
