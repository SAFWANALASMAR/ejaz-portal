<?php

namespace App\Actions\Fortify;

use App\Enums\ActivityEvent;
use App\Models\User;
use App\Notifications\TwoFactorRotationRefused;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication as BaseEnableTwoFactorAuthentication;

/**
 * سر التحقق الثنائي المؤكد لا يُستبدل بطلب (force) من الجلسة نفسها (الفحص الأمني، F8): من يملك الجلسة
 * وكلمة المرور كان يدوّره ويقرأ الرمز الجديد، فيُقفل صاحب الحساب الذي لا يستطيع إيقافه لدوره. والمحاولة
 * تُسجَّل في السجل الأمني ويُشعَر بها صاحب الحساب. والإعداد الأول، أو قبل التأكيد، كما هو في Fortify. ولا
 * شاشة بعدُ لتصفير التحقق الثنائي من اللوحة (ما بقي في تقرير الفحص).
 */
class EnableTwoFactorAuthentication extends BaseEnableTwoFactorAuthentication
{
    /**
     * @param  mixed  $user
     * @param  bool  $force
     */
    public function __invoke($user, $force = false): void
    {
        if ($force === true && $user instanceof User && $user->hasEnabledTwoFactorAuthentication()) {
            activity('security')->causedBy($user)->performedOn($user)
                ->event(ActivityEvent::TwoFactorRotationRefused->value)
                ->withProperties(['ip' => request()->ip()])
                ->log(ActivityEvent::TwoFactorRotationRefused->value);

            $user->notify(new TwoFactorRotationRefused);

            abort(403);
        }

        parent::__invoke($user, $force);
    }
}
