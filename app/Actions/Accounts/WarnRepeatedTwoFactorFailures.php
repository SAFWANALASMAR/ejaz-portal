<?php

namespace App\Actions\Accounts;

use App\Enums\ActivityEvent;
use App\Enums\RoleName;
use App\Models\User;
use App\Notifications\TwoFactorFailuresWarning;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * رمز تحقق ثنائي خاطئ بعد كلمة مرور صحيحة: قد تكون كلمة المرور مسرّبة. سقوف المحاولات (FortifyServiceProvider)
 * تمنع التخمين، وهذا يُعلم المشرف العام حين تكثر الأخطاء على حساب في اليوم (docs/monitoring.md): تنبيه
 * واحد لكل حساب في اليوم عند بلوغ العتبة، بالبريد، وفي السجل الأمني.
 */
class WarnRepeatedTwoFactorFailures
{
    public function handle(User $user): void
    {
        $threshold = (int) config('security.two_factor_alert_threshold');

        if ($threshold < 1) {
            return;
        }

        // اليوم بتوقيت الرياض كما يقرؤه المشرف
        $day = now(config('app.display_timezone'))->toDateString();
        $key = "two-factor-failures:{$user->id}:{$day}";
        Cache::add($key, 0, now()->addDay());
        $failures = (int) Cache::increment($key);

        if ($failures < $threshold || ! Cache::add("two-factor-failures-alert:{$user->id}:{$day}", true, now()->addDay())) {
            return;
        }

        Log::warning('Repeated wrong two-factor codes on one account.', ['user' => $user->id, 'failures' => $failures]);

        activity('security')->performedOn($user)
            ->event(ActivityEvent::TwoFactorFailuresAlerted->value)
            ->withProperties(['failures' => $failures])
            ->log(ActivityEvent::TwoFactorFailuresAlerted->value);

        Notification::send(
            User::role(RoleName::SuperAdmin->value)->where('is_active', true)->get(),
            new TwoFactorFailuresWarning($user, $failures),
        );
    }
}
