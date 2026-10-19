<?php

namespace App\Actions\Messaging;

use App\Contracts\SmsGateway;
use App\Contracts\WhatsAppNotifier;
use App\Enums\ActivityEvent;
use App\Models\MessageLog;
use App\Models\MessagingChannel;
use App\Models\User;
use App\Services\Messaging\MessagingException;
use App\Services\WhatsApp\WhatsAppMessage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * رسالة اختبار من «قنوات التواصل» إلى رقم يُدخله المشرف العام، فوراً لا في الطابور ليرى النتيجة:
 * نص ثابت في الرسائل النصية، وقالب معتمد يسمّيه في واتساب. بحد لكل مشرف في الساعة، وتُسجَّل في سجل
 * العمليات بحالتها بلا الرقم.
 */
class SendTestMessage
{
    /**
     * @param  array{phone?: mixed, template?: mixed}  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, string $channel, array $data): MessageLog
    {
        if (! $actor->can('messaging.update')) {
            throw new AuthorizationException;
        }

        $validated = Validator::make([...$data, 'channel' => $channel], [
            'channel' => ['required', Rule::in([MessagingChannel::SMS, MessagingChannel::WHATSAPP])],
            // جوال سعودي وحده بالصيغة الدولية، فلا تُرسل رسائل اختبار إلى أرقام دولية مكلفة
            'phone' => ['required', 'string', 'regex:/^\+9665\d{8}\z/'],
            'template' => [Rule::requiredIf($channel === MessagingChannel::WHATSAPP), 'nullable', 'string', 'max:512', 'regex:/^[a-z0-9_]+\z/'],
        ], attributes: [
            'phone' => __('messaging.test.phone'),
            'template' => __('messaging.test.template'),
        ])->validate();

        // لكل مشرف في الساعة، وللبوابة كلها في اليوم
        $limits = [
            ['messaging-test:'.$actor->id, (int) config('messaging.tests_per_hour'), 3600],
            ['messaging-test:all', (int) config('messaging.tests_per_day'), 86400],
        ];

        foreach ($limits as [$key, $max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                throw ValidationException::withMessages(['phone' => __('messaging.test.too_many', ['minutes' => (int) ceil(RateLimiter::availableIn($key) / 60)])]);
            }
        }

        foreach ($limits as [$key, , $decay]) {
            RateLimiter::hit($key, $decay);
        }

        $phone = (string) $validated['phone'];
        $deliver = app(DeliverMessage::class);

        $send = $channel === MessagingChannel::SMS
            ? fn () => app(SmsGateway::class)->send($phone, __('messaging.test.sms_body'))
            : fn () => app(WhatsAppNotifier::class)->send($phone, new WhatsAppMessage('test', [], __('messaging.test.sms_body'), (string) $validated['template']));

        try {
            $log = $deliver->handle($channel, $phone, 'test', $send);
        } catch (MessagingException $exception) {
            // الفشل مسجّل بسببه في سجل هذه الرسالة نفسها، ويُعرض للمشرف
            $log = $exception->log ?? throw $exception;
        }

        activity('messaging')->causedBy($actor)
            ->event(ActivityEvent::MessagingTestSent->value)
            ->withProperties(['channel' => $channel, 'provider' => $log->provider, 'status' => $log->status->value, 'error' => $log->error])
            ->log(ActivityEvent::MessagingTestSent->value);

        return $log;
    }
}
