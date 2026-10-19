<?php

namespace App\Services\WhatsApp;

/**
 * رسالة واتساب: الاستعمال (يحدد اسم القالب المعتمد من الإعدادات)، ومتغيرات نص القالب بترتيبها،
 * والنص الكامل للتطوير والسجل. وtemplate اسم قالب صريح يتقدم على الاستعمال (رسالة الاختبار من اللوحة).
 */
final readonly class WhatsAppMessage
{
    /**
     * @param  list<string>  $parameters
     */
    public function __construct(
        public string $usage,
        public array $parameters,
        public string $text,
        public ?string $template = null,
    ) {}
}
