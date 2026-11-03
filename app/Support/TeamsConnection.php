<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use SensitiveParameter;

/**
 * إعداد ربط تيمز من اللوحة (إعداد online_meetings، للمشرف العام وحده): المستأجر، ومعرّف التطبيق، والسر
 * مشفّراً، وبريد المنظِّم. والسر لا يُطبع ولا يدخل أثر الاستثناء (SensitiveParameter و__debugInfo).
 */
final class TeamsConnection
{
    public const SETTING = 'online_meetings';

    public function __construct(
        public readonly string $tenantId,
        public readonly string $clientId,
        #[SensitiveParameter] private readonly string $clientSecret,
        public readonly string $organizer,
    ) {}

    /**
     * الإعداد المفعّل الكامل، أو null فيبقى الربط مطفأً.
     */
    public static function current(): ?self
    {
        return (bool) (Setting::group(self::SETTING)['enabled'] ?? false) ? self::stored() : null;
    }

    /**
     * هل ينشئ الربط روابط الاجتماعات آلياً الآن؟ كما يحل AppServiceProvider الواجهة: الوهمي خارج الإنتاج،
     * أو Graph بإعداد مفعّل كامل. ومطفأً يلزم الاجتماعَ عن بعد رابطٌ يدوي (SaveMeeting).
     */
    public static function isOn(): bool
    {
        return match (config('services.online_meetings.driver')) {
            'fake' => ! app()->isProduction(),
            'graph' => self::current() !== null,
            default => false,
        };
    }

    /**
     * المحفوظ ولو كان مطفأً (لاختبار الاتصال قبل التفعيل)، أو null إن نقص. والسر الذي لا يُفك (مفتاح التطبيق
     * تغيّر) نقص.
     */
    public static function stored(): ?self
    {
        $settings = Setting::group(self::SETTING);
        $values = array_map(fn (string $key): string => trim((string) ($settings[$key] ?? '')), ['tenant_id', 'client_id', 'organizer']);

        try {
            $secret = filled($settings['client_secret'] ?? null) ? Crypt::decryptString((string) $settings['client_secret']) : '';
        } catch (DecryptException) {
            $secret = '';
        }

        if (in_array('', [...$values, $secret], true)) {
            return null;
        }

        return new self($values[0], $values[1], $secret, $values[2]);
    }

    public function secret(): string
    {
        return $this->clientSecret;
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['tenantId' => $this->tenantId, 'clientId' => $this->clientId, 'organizer' => $this->organizer];
    }
}
