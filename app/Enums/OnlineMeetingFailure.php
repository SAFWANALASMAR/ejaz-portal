<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * سبب تعذر ربط اجتماع بتيمز: يُحفظ على الاجتماع ويُعرض للمسؤول، ويُعرض في «اختبار الاتصال». ولا يحمل
 * رد Microsoft نفسه، فلا يصل إلى السجل أو الشاشة إلا السبب.
 */
enum OnlineMeetingFailure: string implements HasLabel
{
    // الربط مطفأ أو ناقص في اللوحة
    case NotConfigured = 'not_configured';
    // المستأجر أو معرّف التطبيق أو السر مرفوض
    case InvalidCredentials = 'invalid_credentials';
    // التطبيق بلا صلاحية Calendars.ReadWrite، أو صندوق المنظِّم خارج نطاقه
    case AccessDenied = 'access_denied';
    // المنظِّم بلا حساب أو بلا صندوق بريد في Exchange Online
    case OrganizerNotFound = 'organizer_not_found';
    // الحدث حُذف من التقويم مباشرة
    case EventNotFound = 'event_not_found';
    // رفضت Graph الطلب نفسه (400)، فلا تصلحه الإعادة
    case InvalidRequest = 'invalid_request';
    case Throttled = 'throttled';
    case Timeout = 'timeout';
    case ServiceError = 'service_error';
    // رد بلا معرّف أو بلا رابط، أو رفض آخر
    case Unexpected = 'unexpected';

    public function getLabel(): string
    {
        return __('assembly.online_failures.'.$this->value);
    }

    /**
     * عابر يزول بإعادة المحاولة آلياً، وغيره لا يزول إلا بتصحيح الإعداد في اللوحة أو في Microsoft 365.
     */
    public function isTransient(): bool
    {
        return in_array($this, [self::Throttled, self::Timeout, self::ServiceError, self::Unexpected], true);
    }
}
