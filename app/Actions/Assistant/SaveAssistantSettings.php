<?php

namespace App\Actions\Assistant;

use App\Enums\ActivityEvent;
use App\Enums\AssistantProvider;
use App\Models\AssistantSettings;
use App\Models\User;
use App\Rules\PlainText;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * حفظ إعدادات المساعد (ai_assistant.manage، للمشرف العام وحده). المفتاح الجديد يُحفظ مشفّراً، والفارغ يُبقي المحفوظ،
 * و«حذف المفتاح» يمسحه. وتغيّر المفتاح يُسجَّل حدثاً بلا قيمته، وبقية الحقول بسجل النموذج (بلا المفتاح).
 * والتوليد لا يُشغَّل إلا بمزود مضبوط وسقف شهري وسعر الوحدات، فلا تكلفة بلا حد.
 */
class SaveAssistantSettings
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, array $data): AssistantSettings
    {
        if (! $actor->can('ai_assistant.manage')) {
            throw new AuthorizationException;
        }

        $validated = self::validate($data);
        $settings = AssistantSettings::query()->orderBy('id')->first() ?? new AssistantSettings;
        $newKey = $validated['api_key'] ?? null;
        $clearKey = (bool) ($validated['clear_api_key'] ?? false);
        unset($validated['api_key'], $validated['clear_api_key']);

        // مفتاح شُفّر بمفتاح تطبيق قديم لا يُفك، فلا يُقارن بالجديد: يُمسح أولاً ليُكتب غيره
        if ($settings->exists && $settings->storedKeyUnreadable()) {
            AssistantSettings::query()->whereKey($settings->getKey())->update(['api_key' => null]);
            $settings->refresh();
        }

        $keyChanged = filled($newKey) || ($clearKey && $settings->hasStoredKey());
        $saved = $settings->exists ? clone $settings : null;

        $settings->fill($validated);
        self::guardKeyDestination($settings, $saved, filled($newKey) || $clearKey);

        if (filled($newKey)) {
            $settings->api_key = $newKey;
        } elseif ($clearKey) {
            $settings->api_key = null;
        }

        // التوليد في المساعد والتحليل في الإحصائيات يرسلان إلى المزود نفسه بالسقف نفسه، فلكل منهما الشرط نفسه
        $ready = $settings->hasUsableProvider() && $settings->monthly_cap !== null && $settings->input_price !== null && $settings->output_price !== null;

        foreach (['generation_enabled', 'analysis_enabled'] as $switch) {
            if ($settings->{$switch} && ! $ready) {
                throw ValidationException::withMessages([$switch => __('assistant.admin.settings.errors.generation_requires')]);
            }
        }

        DB::transaction(function () use ($settings, $keyChanged, $actor): void {
            $settings->save();

            if ($keyChanged) {
                activity('assistant')
                    ->causedBy($actor)
                    ->performedOn($settings)
                    ->event(ActivityEvent::AssistantKeyChanged->value)
                    ->withProperties(['key_saved' => filled($settings->api_key)])
                    ->log(ActivityEvent::AssistantKeyChanged->value);
            }
        });

        return $settings;
    }

    /**
     * لا يُرسل المفتاح إلا إلى نطاق مسموح في .env، والمفتاح المحفوظ لا يُرسل إلى مزود أو نطاق غير الذي حُفظ له: يُكتب
     * من جديد عند تغييرهما، فلا تكفي جلسة مشرف مسروقة لتحويله إلى خادم آخر (المراجعة الأمنية، م2).
     *
     * @throws ValidationException
     */
    public static function guardKeyDestination(AssistantSettings $settings, ?AssistantSettings $saved, bool $keyTyped): void
    {
        if ($settings->provider === null || $settings->provider === AssistantProvider::Fake || $settings->baseUrl() === null) {
            return;
        }

        if (! $settings->hostAllowed()) {
            throw ValidationException::withMessages(['base_url' => __('assistant.admin.settings.errors.host_not_allowed', ['hosts' => implode('، ', (array) config('assistant.allowed_hosts'))])]);
        }

        $moved = $saved !== null && ($saved->provider !== $settings->provider || $saved->host() !== $settings->host());

        if ($moved && ! $keyTyped && $saved->hasStoredKey()) {
            throw ValidationException::withMessages(['api_key' => __('assistant.admin.settings.errors.retype_key')]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function validate(array $data): array
    {
        $providers = array_map(fn (AssistantProvider $provider): string => $provider->value, AssistantProvider::available());

        $validated = Validator::make($data, [
            'is_enabled' => ['boolean'],
            'generation_enabled' => ['boolean'],
            'analysis_enabled' => ['boolean'],
            'provider' => ['nullable', Rule::in($providers)],
            'model' => ['nullable', 'string', 'max:150', 'regex:/^[A-Za-z0-9._:\/@\-]+$/'],
            'base_url' => ['nullable', 'string', 'max:300', function (string $attribute, mixed $value, Closure $fail): void {
                if (! self::validBaseUrl((string) $value)) {
                    $fail(__('assistant.admin.settings.errors.base_url'));
                }
            }],
            'api_key' => ['nullable', 'string', 'max:500', 'regex:/^[\x21-\x7E]+$/'],
            'clear_api_key' => ['boolean'],
            'monthly_cap' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'input_price' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'output_price' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'welcome_text' => ['nullable', 'string', 'max:600', new PlainText(multiline: true)],
            'suggested_questions' => ['nullable', 'array', 'max:6'],
            'suggested_questions.*' => ['string', 'max:150', new PlainText],
            'log_retention_days' => ['nullable', 'integer', 'min:1', 'max:'.AssistantSettings::MAX_RETENTION_DAYS],
        ], attributes: [
            'provider' => __('assistant.admin.settings.provider'),
            'model' => __('assistant.admin.settings.model'),
            'base_url' => __('assistant.admin.settings.base_url'),
            'api_key' => __('assistant.admin.settings.api_key'),
            'monthly_cap' => __('assistant.admin.settings.monthly_cap'),
            'input_price' => __('assistant.admin.settings.input_price'),
            'output_price' => __('assistant.admin.settings.output_price'),
            'welcome_text' => __('assistant.admin.settings.welcome_text'),
            'suggested_questions' => __('assistant.admin.settings.suggested_questions'),
            'log_retention_days' => __('assistant.admin.settings.log_retention_days'),
        ])->validate();

        $validated['suggested_questions'] = array_values(array_filter(array_map('trim', (array) ($validated['suggested_questions'] ?? [])), fn (string $question): bool => $question !== ''));
        $validated['base_url'] = filled($validated['base_url'] ?? null) ? rtrim((string) $validated['base_url'], '/') : null;

        foreach (['model', 'welcome_text'] as $field) {
            $validated[$field] = filled($validated[$field] ?? null) ? trim((string) $validated[$field]) : null;
        }

        return $validated;
    }

    /**
     * رابط https كامل بلا مستخدم ولا استعلام ولا جزء. وفي غير الإنتاج يُقبل http لخادم نموذج تجريبي.
     */
    private static function validBaseUrl(string $url): bool
    {
        $parts = parse_url($url);
        $schemes = app()->isProduction() ? ['https'] : ['https', 'http'];

        return is_array($parts)
            && in_array(strtolower($parts['scheme'] ?? ''), $schemes, true)
            && ($parts['host'] ?? '') !== ''
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && ! isset($parts['query'])
            && ! isset($parts['fragment'])
            && preg_match('/[\s\\\\]/', $url) !== 1;
    }
}
