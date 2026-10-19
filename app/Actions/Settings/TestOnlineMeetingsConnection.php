<?php

namespace App\Actions\Settings;

use App\Enums\ActivityEvent;
use App\Enums\OnlineMeetingFailure;
use App\Exceptions\OnlineMeetingFailed;
use App\Models\User;
use App\Services\OnlineMeetings\GraphOnlineMeetings;
use App\Support\TeamsConnection;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * «اختبار الاتصال» بالإعداد المحفوظ، ولو كان مطفأً، فيُختبر قبل التفعيل (للمشرف العام — online_meetings.update):
 * مفتاح جديد من Microsoft ثم قراءة تقويم المنظِّم، بلا إنشاء شيء. ويعيد سبب التعذر أو null، ويُسجَّل بنتيجته.
 */
class TestOnlineMeetingsConnection
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException أكثر من خمسة اختبارات في الدقيقة
     */
    public function handle(User $actor): ?OnlineMeetingFailure
    {
        if (! $actor->can('online_meetings.update')) {
            throw new AuthorizationException;
        }

        // كل اختبار يطلب مفتاحاً جديداً من Microsoft، فخمسة في الدقيقة
        if (! RateLimiter::attempt('online-meetings-test:'.$actor->id, 5, fn (): bool => true)) {
            throw ValidationException::withMessages(['connection' => __('settings.online_meetings.test_throttled')]);
        }

        $connection = TeamsConnection::stored();

        try {
            if ($connection === null) {
                throw new OnlineMeetingFailed(OnlineMeetingFailure::NotConfigured);
            }

            (new GraphOnlineMeetings($connection))->verify();
            $failure = null;
        } catch (OnlineMeetingFailed $failed) {
            $failure = $failed->reason;
        }

        activity('security')->causedBy($actor)
            ->event(ActivityEvent::OnlineMeetingsConnectionTested->value)
            ->withProperties(['result' => $failure->value ?? 'ok'])
            ->log(ActivityEvent::OnlineMeetingsConnectionTested->value);

        return $failure;
    }
}
