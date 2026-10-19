<?php

namespace App\Services\Assistant;

use RuntimeException;

/**
 * تعذّر المزود: مهلة، أو خطأ اتصال، أو رد بخطأ أو بلا نص. الزائر يرى «المساعد مشغول»، والسبب في سجل التطبيق
 * بلا السؤال ولا المفتاح.
 */
class AssistantProviderException extends RuntimeException {}
