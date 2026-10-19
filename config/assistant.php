<?php

/*
| المساعد الذكي — requirements/10-ai-assistant.md وdocs/guides/ai-assistant.md
|
| التشغيل والمزود والنموذج والسقف من لوحة الإدارة (للمشرف العام). وهنا الحدود الفنية الثابتة، ومفتاح المزود متى
| أُريد خارج القاعدة: AI_ASSISTANT_ANTHROPIC_KEY أو AI_ASSISTANT_OPENAI_KEY يُقدَّم لمزوده على المحفوظ مشفّراً في اللوحة.
*/

return [

    // مفتاح لكل مزود، فلا يُرسل مفتاح مزود إلى غيره إن تغيّر المزود في اللوحة (مراجعة المشرف، ٧)
    'api_keys' => [
        'anthropic' => env('AI_ASSISTANT_ANTHROPIC_KEY'),
        'openai_compatible' => env('AI_ASSISTANT_OPENAI_KEY'),
    ],

    // اسم حد المخرج في Chat Completions: max_completion_tokens للنماذج الأحدث في OpenAI، وmax_tokens لخوادم أقدم
    // متوافقة معها (مراجعة المشرف، ٦)
    'openai_token_parameter' => env('AI_ASSISTANT_OPENAI_TOKEN_PARAMETER', 'max_completion_tokens'),

    // مهلة الرد القصوى من المزود بالثواني، ومهلة الاتصال ضمنها
    'timeout' => (int) env('AI_ASSISTANT_TIMEOUT', 15),
    'connect_timeout' => 5,

    // الطلبات المتزامنة إلى المزود من البوابة كلها، وما زاد يُرد عليه «المساعد مشغول»
    'concurrency' => (int) env('AI_ASSISTANT_CONCURRENCY', 4),

    // حد الأسئلة لكل زائر (عنوانه في IPv4، أو /64 في IPv6) في نافذة بالدقائق، وحد أوسع لشبكته (/24 في IPv4، و/48 في
    // IPv6) فلا يُلتف عليه بتدوير العناوين. والعنوان في مفتاح الحد وحده، لا في السجل
    'per_visitor' => 10,
    'per_network' => 60,
    'per_visitor_minutes' => 10,

    // ما يُرسل إلى المزود من البوابة كلها في الدقيقة، وحصة اليوم من السقف الشهري (خُمسه)، فلا يستنزف مصدر واحد
    // الشهر في ساعات (المراجعة الأمنية، م3)
    'global_per_minute' => (int) env('AI_ASSISTANT_PER_MINUTE', 30),
    'daily_share' => 0.2,

    // نطاقات المزودين المسموح أن يُرسل إليها المفتاح، تُضبط في .env على الخادم وحده لا من اللوحة (المراجعة الأمنية، م2).
    // ونموذج مستضاف داخل المملكة يُضاف نطاقه هنا قبل اختياره
    'allowed_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('AI_ASSISTANT_ALLOWED_HOSTS', 'api.anthropic.com,api.openai.com'))))),

    // الطول الأقصى للسؤال بالأحرف
    'max_question' => 500,

    // المقاطع المرسلة مع السؤال، وطول المقطع بالأحرف، وأقصى ما يكتبه المزود
    'max_chunks' => 6,
    'chunk_chars' => 1200,
    // ويُحسب بأقصاه على السقف عند انتهاء المهلة (CheckAssistantBudget::timeoutUsage)
    'max_output_tokens' => 600,

    // الأسئلة المقترحة الافتراضية من عناوين الدليل المفعّلة متى لم تُضبط في الإعدادات
    'suggestions' => 4,

];
