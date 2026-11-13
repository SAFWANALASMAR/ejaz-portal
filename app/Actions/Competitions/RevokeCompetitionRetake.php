<?php

namespace App\Actions\Competitions;

use App\Models\CompetitionRetake;
use App\Models\CompetitionRound;
use App\Models\User;
use App\Support\CompetitionAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * إلغاء إعادة اختبار مُنحت ولم يبدأها صاحبها (مراجعة #261): لمن يمنحها (competition.allow_retake). تُحذف الإعادة ويبقى حذفها
 * في سجل العمليات، وتبقى محاولته الأصلية كما هي، ويُفك بها رفض النشر (PublishRoundResults). والمستعملة لا تُلغى، لأن محاولتها
 * الجديدة قائمة والأصلية مؤرشفة فيها.
 */
class RevokeCompetitionRetake
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, CompetitionRetake $retake): void
    {
        if (! CompetitionAccess::allows($actor, GrantCompetitionRetake::PERMISSION)) {
            throw new AuthorizationException(__('competitions.student.admin.not_allowed'));
        }

        DB::transaction(function () use ($retake): void {
            // صف المرحلة أولاً كالمنح والنشر، ثم الإعادة: فلا يُلغى ما بدأه الطالب في اللحظة نفسها
            CompetitionRound::query()->whereKey($retake->round_id)->lockForUpdate()->firstOrFail();
            $locked = CompetitionRetake::query()->whereKey($retake->id)->lockForUpdate()->first();

            if ($locked === null || $locked->used_at !== null) {
                throw ValidationException::withMessages(['retake' => __('competitions.student.retake.revoke_used')]);
            }

            $locked->delete();
        });
    }
}
