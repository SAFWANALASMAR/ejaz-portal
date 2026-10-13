<?php

namespace App\Support;

use App\Models\CompetitionSubmission;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * قفل صف المشاركة حتى نهاية المعاملة، ورفضه إن لم تعد مسودة (أُرسلت أو حُذفت بعد قراءتها). فيتسلسل
 * ما يغيّر مقاطعها (البدء والجزء والإكمال) مع الإرسال، والفحص على الحالة المقفولة لا على نسخة قديمة.
 * وترتيب الأقفال ثابت: المشاركة ثم المقطع.
 */
final class SubmissionLock
{
    /**
     * @throws AuthorizationException
     */
    public static function draft(int $id): CompetitionSubmission
    {
        /** @var CompetitionSubmission $locked */
        $locked = CompetitionSubmission::query()->withTrashed()->whereKey($id)->lockForUpdate()->firstOrFail();

        if (! $locked->isDraft() || $locked->trashed()) {
            throw new AuthorizationException;
        }

        return $locked;
    }
}
