<?php

namespace App\Actions\Competitions;

use App\Enums\ActivityEvent;
use App\Models\CompetitionDraw;
use App\Models\CompetitionDrawVoid;
use App\Models\CompetitionRound;
use App\Models\User;
use App\Support\CompetitionAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * إلغاء سحب المتساوين في مرحلة بسبب مكتوب يُسجَّل (مراجعة المشرف): لمن يدير نتائج المسابقة، فيُتاح سحب جديد للمرحلة
 * والفئة، ويبقى الملغى ظاهراً بسببه في سجل السحب. ومرة واحدة لكل مرحلة وفئة، فلا يُكرر السحب حتى نتيجة بعينها:
 * ما بعدها قرار من الجمعية خارج النظام.
 */
class VoidTieDraw
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, CompetitionDraw $draw, string $reason): CompetitionDrawVoid
    {
        $round = $draw->round;

        if (! $round instanceof CompetitionRound || ! CompetitionAccess::managesResults($actor, $round->competition)) {
            throw new AuthorizationException;
        }

        $reason = trim($reason);

        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => __('competitions.draws.void_reason_rule')]);
        }

        return DB::transaction(function () use ($actor, $draw, $round, $reason): CompetitionDrawVoid {
            // قفل المرحلة يسلسل الإلغاء مع السحب الجديد فيها (RunTieDraw)
            CompetitionRound::query()->whereKey($round->id)->lockForUpdate()->firstOrFail();

            $voidedBefore = CompetitionDrawVoid::query()->whereIn('draw_id', CompetitionDraw::query()
                ->where('round_id', $draw->round_id)
                ->when($draw->category_id === null, fn ($query) => $query->whereNull('category_id'), fn ($query) => $query->where('category_id', $draw->category_id))
                ->select('id'))->exists();

            if ($voidedBefore) {
                throw ValidationException::withMessages(['reason' => __('competitions.student.tie_void_once')]);
            }

            $void = new CompetitionDrawVoid;
            $void->draw_id = $draw->id;
            $void->reason = $reason;
            $void->voided_by = $actor->id;
            $void->voided_at = now();
            $void->save();

            activity('competitions')->performedOn($draw)->causedBy($actor)
                ->event(ActivityEvent::DrawVoided->value)
                ->withProperties(['reason' => $reason, 'round_id' => $draw->round_id, 'category_id' => $draw->category_id])
                ->log(ActivityEvent::DrawVoided->value);

            return $void;
        });
    }
}
