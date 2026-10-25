<?php

namespace App\Support;

use App\Models\CompetitionAttempt;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * صف المحاولة مقفولاً وهي جارية لم ينته وقتها، داخل معاملة قائمة. فلا يكتب طلبان متزامنان أحدهما فوق
 * الآخر، ولا تُقبل إجابة بعد التسليم أو انتهاء الوقت.
 */
final class AttemptLock
{
    /**
     * @throws AuthorizationException
     */
    public static function running(CompetitionAttempt $attempt): CompetitionAttempt
    {
        /** @var CompetitionAttempt $locked */
        $locked = CompetitionAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

        if (! $locked->isInProgress()) {
            throw new AuthorizationException(__('competitions.student.already_submitted'));
        }

        if ($locked->expired()) {
            throw new AuthorizationException(__('competitions.student.time_up'));
        }

        $locked->setRelation('round', $attempt->round);

        return $locked;
    }
}
