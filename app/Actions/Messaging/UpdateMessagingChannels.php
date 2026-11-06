<?php

namespace App\Actions\Messaging;

use App\Enums\ActivityEvent;
use App\Enums\SmsProvider;
use App\Models\MessagingChannel;
use App\Models\User;
use App\Rules\PlainText;
use App\Services\Messaging\MessagingSettings;
use BackedEnum;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * حفظ «قنوات التواصل» (messaging.update، للمشرف العام وحده): مزود كل قناة وإعداداتها وتفعيل استعمالاتها.
 * المفاتيح: الحقل الفارغ يُبقي المحفوظ، وclear_<المفتاح> يحذفه، والجديد يُشفَّر في القاعدة. ولا تُكتب
 * قيمة مفتاح في سجل العمليات: يُسجَّل اسم ما تغيّر وحده.
 */
class UpdateMessagingChannels
{
    public const SECRETS = [
        MessagingChannel::SMS => ['api_key'],
        MessagingChannel::WHATSAPP => ['access_token', 'app_secret', 'verify_token'],
    ];

    /**
     * @param  array{sms?: array<string, mixed>, whatsapp?: array<string, mixed>}  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, array $data): void
    {
        if (! $actor->can('messaging.update')) {
            throw new AuthorizationException;
        }

        $secretRules = ['nullable', 'string', 'max:1000', 'regex:/^\S+\z/'];

        $validated = Validator::make($data, [
            'sms' => ['sometimes', 'array'],
            'sms.provider' => ['nullable', Rule::enum(SmsProvider::class)],
            // اسم المرسل كما فُعّل عند المزود: حروف وأرقام ومسافة ونقطة وشرطة
            'sms.sender' => ['nullable', 'string', 'max:20', new PlainText, 'regex:/^[\p{L}\p{N} .\-]+\z/u'],
            'sms.username' => ['nullable', 'string', 'max:100', new PlainText],
            'sms.api_key' => $secretRules,
            'sms.clear_api_key' => ['nullable', 'boolean'],
            'sms.usages.request_updates' => ['nullable', 'boolean'],

            'whatsapp' => ['sometimes', 'array'],
            'whatsapp.provider' => ['nullable', Rule::in(['cloud'])],
            'whatsapp.phone_number_id' => ['nullable', 'string', 'regex:/^\d{5,30}\z/'],
            'whatsapp.graph_version' => ['nullable', 'string', 'regex:/^v\d{1,3}\.\d{1,3}\z/'],
            'whatsapp.language' => ['nullable', 'string', 'regex:/^[a-z]{2,3}(_[A-Z]{2})?\z/'],
            // أسماء قوالب Meta: حروف لاتينية صغيرة وأرقام وشرطة سفلية
            'whatsapp.templates.exam_receipt' => ['nullable', 'string', 'max:512', 'regex:/^[a-z0-9_]+\z/'],
            'whatsapp.templates.otp' => ['nullable', 'string', 'max:512', 'regex:/^[a-z0-9_]+\z/'],
            'whatsapp.access_token' => $secretRules,
            'whatsapp.app_secret' => $secretRules,
            'whatsapp.verify_token' => $secretRules,
            'whatsapp.clear_access_token' => ['nullable', 'boolean'],
            'whatsapp.clear_app_secret' => ['nullable', 'boolean'],
            'whatsapp.clear_verify_token' => ['nullable', 'boolean'],
            'whatsapp.usages.exam_receipt' => ['nullable', 'boolean'],
            'whatsapp.usages.otp' => ['nullable', 'boolean'],
        ], attributes: [
            'sms.provider' => __('messaging.fields.provider'),
            'sms.sender' => __('messaging.sms.sender'),
            'sms.username' => __('messaging.sms.username'),
            'sms.api_key' => __('messaging.sms.api_key'),
            'whatsapp.phone_number_id' => __('messaging.whatsapp.phone_number_id'),
            'whatsapp.graph_version' => __('messaging.whatsapp.graph_version'),
            'whatsapp.language' => __('messaging.whatsapp.language'),
            'whatsapp.templates.exam_receipt' => __('messaging.whatsapp.templates.exam_receipt'),
            'whatsapp.templates.otp' => __('messaging.whatsapp.templates.otp'),
            'whatsapp.access_token' => __('messaging.whatsapp.access_token'),
            'whatsapp.app_secret' => __('messaging.whatsapp.app_secret'),
            'whatsapp.verify_token' => __('messaging.whatsapp.verify_token'),
        ])->validate();

        DB::transaction(function () use ($actor, $validated): void {
            if (array_key_exists('sms', $validated)) {
                $sms = (array) $validated['sms'];

                $this->save($actor, MessagingChannel::SMS, $sms, [
                    'sender' => self::clean($sms['sender'] ?? null),
                    'username' => self::clean($sms['username'] ?? null),
                    'usages' => ['request_updates' => (bool) ($sms['usages']['request_updates'] ?? false)],
                ]);
            }

            if (array_key_exists('whatsapp', $validated)) {
                $whatsapp = (array) $validated['whatsapp'];

                $this->save($actor, MessagingChannel::WHATSAPP, $whatsapp, [
                    'phone_number_id' => self::clean($whatsapp['phone_number_id'] ?? null),
                    'graph_version' => self::clean($whatsapp['graph_version'] ?? null),
                    'language' => self::clean($whatsapp['language'] ?? null),
                    'templates' => [
                        'exam_receipt' => self::clean($whatsapp['templates']['exam_receipt'] ?? null),
                        'otp' => self::clean($whatsapp['templates']['otp'] ?? null),
                    ],
                    'usages' => [
                        'exam_receipt' => (bool) ($whatsapp['usages']['exam_receipt'] ?? false),
                        'otp' => (bool) ($whatsapp['usages']['otp'] ?? false),
                    ],
                ]);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $options
     */
    private function save(User $actor, string $channel, array $input, array $options): void
    {
        $model = MessagingChannel::query()->lockForUpdate()->firstOrNew(['channel' => $channel]);
        $secrets = $model->storedSecrets();
        $changed = [];

        foreach (self::SECRETS[$channel] as $name) {
            $new = self::clean($input[$name] ?? null);

            if ($new !== null) {
                $secrets[$name] = $new;
                $changed[] = $name;
            } elseif (($input['clear_'.$name] ?? false) && array_key_exists($name, $secrets)) {
                unset($secrets[$name]);
                $changed[] = $name;
            }
        }

        // الاستعمال يُحفظ إن ضُبط من اللوحة قبلُ أو غيّره المسؤول عن المعمول به، وما لم يُلمس يبقى لمفتاحه في البيئة كما
        // يقول التلميح، فلا يثبّت أول حفظ قيمة البيئة (مراجعة ما بعد v0.12.0، ملاحظة مهمة ٣)
        $options['usages'] = array_filter(
            (array) ($options['usages'] ?? []),
            fn (bool $on, string $usage): bool => is_bool($model->option('usages.'.$usage)) || $on !== MessagingSettings::usageEnabled($channel, $usage),
            ARRAY_FILTER_USE_BOTH,
        );

        $model->fill([
            'provider' => self::clean($input['provider'] ?? null),
            'options' => $options,
            'secrets' => $secrets === [] ? null : $secrets,
        ])->save();

        if ($changed !== []) {
            activity('messaging')->causedBy($actor)->performedOn($model)
                ->event(ActivityEvent::MessagingSecretsChanged->value)
                ->withProperties(['channel' => $channel, 'secrets' => $changed])
                ->log(ActivityEvent::MessagingSecretsChanged->value);
        }
    }

    private static function clean(mixed $value): ?string
    {
        // قائمة المزود في اللوحة تعيد الحالة Enum
        if ($value instanceof BackedEnum) {
            $value = (string) $value->value;
        }

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
