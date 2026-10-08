<?php

namespace App\Support\Payments;

use InvalidArgumentException;

/**
 * إشعار بتوقيع صحيح لحدث لا تعالجه البوابة (مثل أحداث توثيق البطاقة): يُقبل بلا أثر حتى لا تعيد
 * البوابة إرساله، ولا يُقيَّد أمنياً.
 */
final class UnsupportedWebhook extends InvalidArgumentException {}
