<?php

namespace App\Actions\Users;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * إعادة إخفاء بيانات حساب بعد الاستعادة من نسخة احتياطية (scripts/restore.md): أُخفيت بياناته بعد وقت النسخة،
 * فعاد في القاعدة المستعادة ببياناته. يُنفَّذ بإجراء الإخفاء نفسه (AnonymizeUser) وصلاحيته، ثم يُعاد وقت الإخفاء
 * الأصلي، فتُحسب مدة الاحتفاظ منه (ejaz:purge-anonymized-data) لا من يوم الاستعادة.
 */
class ReapplyAnonymization
{
    public function __construct(private readonly AnonymizeUser $anonymize) {}

    /**
     * @return bool false إن كانت بياناته مخفاة أصلاً في القاعدة المستعادة
     *
     * @throws AuthorizationException
     */
    public function handle(User $actor, User $user, CarbonInterface $anonymizedAt): bool
    {
        if ($user->anonymized_at !== null) {
            return false;
        }

        DB::transaction(function () use ($actor, $user, $anonymizedAt): void {
            // طلب الحذف نفسه قد يكون بعد النسخة فلم يُستعد. ووقته لا يتأخر عن الإخفاء
            if ($user->deletion_requested_at === null) {
                $user->forceFill(['deletion_requested_at' => $anonymizedAt])->save();
            }

            $this->anonymize->handle($actor, $user);

            activity()->withoutLogging(fn () => $user->forceFill(['anonymized_at' => $anonymizedAt])->save());
        });

        return true;
    }
}
