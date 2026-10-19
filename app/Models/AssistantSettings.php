<?php

namespace App\Models;

use App\Enums\AssistantProvider;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * إعدادات المساعد الذكي: سجل واحد يُقرأ مع كل صفحة (زر المساعد)، فيُخزَّن في الذاكرة المؤقتة بقيمه الخام، ومنها
 * المفتاح مشفّراً كما في القاعدة. ومفتاح المزود مشفّر (encrypted) ولا يدخل سجل العمليات، ومفتاح المزود المختار
 * في .env (مفتاح لكل مزود، config/assistant.php) يُقدَّم عليه متى وُجد (apiKey()).
 *
 * @property bool $is_enabled
 * @property bool $generation_enabled
 * @property AssistantProvider|null $provider
 * @property string|null $model
 * @property string|null $base_url
 * @property string|null $api_key
 * @property string|null $monthly_cap
 * @property string|null $input_price
 * @property string|null $output_price
 * @property string|null $welcome_text
 * @property array<mixed>|null $suggested_questions
 * @property int|null $log_retention_days
 * @property string|null $cap_alerted_month
 */
#[Fillable([
    'is_enabled', 'generation_enabled', 'provider', 'model', 'base_url', 'api_key', 'monthly_cap', 'input_price',
    'output_price', 'welcome_text', 'suggested_questions', 'log_retention_days', 'cap_alerted_month',
])]
class AssistantSettings extends Model
{
    use LogsActivity;

    public const CACHE_KEY = 'assistant.settings';

    /**
     * مدة الاحتفاظ بسجل المحادثات متى لم تُضبط: مؤقتة حتى تحددها الجمعية (open-questions.md).
     */
    public const DEFAULT_RETENTION_DAYS = 90;

    public const MAX_RETENTION_DAYS = 3650;

    protected $hidden = ['api_key'];

    protected static function booted(): void
    {
        static::saved(fn () => DB::afterCommit(fn () => Cache::forget(self::CACHE_KEY)));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'generation_enabled' => 'boolean',
            'provider' => AssistantProvider::class,
            'api_key' => 'encrypted',
            'monthly_cap' => 'decimal:2',
            'input_price' => 'decimal:4',
            'output_price' => 'decimal:4',
            'suggested_questions' => 'array',
            'log_retention_days' => 'integer',
        ];
    }

    /**
     * السجل الوحيد، أو إعدادات افتراضية غير محفوظة (المساعد معطّل).
     */
    public static function current(): self
    {
        /** @var array<string, mixed> $attributes */
        $attributes = Cache::remember(self::CACHE_KEY, now()->addHour(), fn (): array => static::query()->orderBy('id')->first()?->getAttributes() ?? []);

        if ($attributes === []) {
            return new self(['is_enabled' => false, 'generation_enabled' => false]);
        }

        return (new self)->newFromBuilder($attributes);
    }

    /**
     * مفتاح المزود المختار من .env أولاً، ثم المحفوظ مشفّراً في القاعدة.
     */
    public function apiKey(): ?string
    {
        $environment = $this->environmentKey();

        if ($environment !== null) {
            return $environment;
        }

        return $this->storedKey();
    }

    /**
     * المفتاح المحفوظ في القاعدة وحده، بلا مفتاح البيئة. ومفتاح شُفّر بمفتاح تطبيق قديم (APP_KEY تغيّر) لا يُقرأ: كأنه
     * غير محفوظ، فيُكتب من جديد.
     */
    public function storedKey(): ?string
    {
        try {
            return filled($this->api_key) ? (string) $this->api_key : null;
        } catch (DecryptException) {
            return null;
        }
    }

    /**
     * هل في القاعدة مفتاح محفوظ؟ من القيمة المشفّرة دون فكها.
     */
    public function hasStoredKey(): bool
    {
        return filled($this->getAttributes()['api_key'] ?? null);
    }

    public function storedKeyUnreadable(): bool
    {
        if (! $this->hasStoredKey()) {
            return false;
        }

        try {
            $this->getAttribute('api_key');

            return false;
        } catch (DecryptException) {
            return true;
        }
    }

    /**
     * مفتاح المزود المختار في ملف البيئة، مربوطاً بمزوده: مفتاح Anthropic لا يُرسل إلى المتوافق مع OpenAI ولا العكس.
     */
    public function environmentKey(): ?string
    {
        $key = $this->provider !== null ? (string) config('assistant.api_keys.'.$this->provider->value) : '';

        return $key !== '' ? $key : null;
    }

    /**
     * المزودون الذين لهم مفتاح في ملف البيئة، لتنبيه المشرف في الإعدادات.
     *
     * @return list<AssistantProvider>
     */
    public static function providersWithEnvironmentKey(): array
    {
        return array_values(array_filter(AssistantProvider::cases(), fn (AssistantProvider $provider): bool => (string) config('assistant.api_keys.'.$provider->value) !== ''));
    }

    /**
     * مزود صالح لهذه البيئة بنموذجه ومفتاحه وعنوانه، فيُرسل إليه.
     */
    public function hasUsableProvider(): bool
    {
        $provider = $this->provider;

        if ($provider === null || ! in_array($provider, AssistantProvider::available(), true)) {
            return false;
        }

        if ($provider === AssistantProvider::Fake) {
            return true;
        }

        return filled($this->model) && $this->apiKey() !== null && $this->baseUrl() !== null && $this->hostAllowed();
    }

    /**
     * نطاق العنوان الأساسي من القائمة المسموحة في .env (assistant.allowed_hosts)، فلا يُرسل المفتاح إلى نطاق يُكتب في
     * اللوحة وحدها، ولو من جلسة مشرف مسروقة (المراجعة الأمنية، م2).
     */
    public function hostAllowed(): bool
    {
        $host = strtolower((string) parse_url((string) $this->baseUrl(), PHP_URL_HOST));

        return $host !== '' && in_array($host, array_map('strtolower', (array) config('assistant.allowed_hosts')), true);
    }

    public function host(): ?string
    {
        $host = parse_url((string) $this->baseUrl(), PHP_URL_HOST);

        return is_string($host) && $host !== '' ? strtolower($host) : null;
    }

    public function baseUrl(): ?string
    {
        $url = filled($this->base_url) ? rtrim((string) $this->base_url, '/') : $this->provider?->defaultBaseUrl();

        return $url !== null && $url !== '' ? $url : null;
    }

    public function retentionDays(): int
    {
        return $this->log_retention_days !== null && $this->log_retention_days > 0 ? $this->log_retention_days : self::DEFAULT_RETENTION_DAYS;
    }

    /**
     * المفتاح وشهر التنبيه لا يدخلان سجل العمليات، وتغيّر المفتاح يُسجَّل بلا قيمته (SaveAssistantSettings).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('assistant')
            ->logOnly(['is_enabled', 'generation_enabled', 'provider', 'model', 'base_url', 'monthly_cap', 'input_price', 'output_price', 'welcome_text', 'suggested_questions', 'log_retention_days'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
