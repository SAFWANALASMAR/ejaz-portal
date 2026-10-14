<?php

namespace App\Actions\Competitions;

use App\Enums\ActivityEvent;
use App\Models\CompetitionDraw;
use App\Models\CompetitionDrawVoid;
use App\Models\CompetitionDrawVoidRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * إلغاء سحب معتمد: للمشرف العام وحده، بسبب إلزامي يُسجَّل. فيُتاح سحب جديد، ويبقى الملغى ظاهراً
 * معلَّماً بسببه، فلا يُختار سحب من بين سحوبات خفية.
 *
 * وإلغاء واحد لكل مسابقة بقرار مشرف عام واحد. وما بعده طلب لا إلغاء، يعتمده مشرف عام ثانٍ غير الطالب
 * (ApproveDrawVoid) أو يرفضه بسبب (RejectDrawVoid)، ويسقط بعد سبعة أيام بلا قرار، فلا يكرر مشرف واحد السحب حتى نتيجة بعينها (decisions.md — 14 أكتوبر 2026).
 */
class VoidDraw
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, CompetitionDraw $draw, string $reason): CompetitionDrawVoid|CompetitionDrawVoidRequest
    {
        if (! $actor->can('void', $draw)) {
            throw new AuthorizationException;
        }

        $reason = trim($reason);

        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => __('competitions.draws.void_reason_rule')]);
        }

        return DB::transaction(function () use ($actor, $draw, $reason): CompetitionDrawVoid|CompetitionDrawVoidRequest {
            // قفل المسابقة يسلسل الإلغاءات المتزامنة، فلا يمر إلغاءان مباشران في مسابقة واحدة
            $draw->competition()->lockForUpdate()->first();

            if (CompetitionDrawVoid::query()->where('draw_id', $draw->id)->exists()) {
                throw ValidationException::withMessages(['reason' => __('competitions.draws.already_void')]);
            }

            // طلب معلّق واحد للسحب، والمرفوض والساقط لا يمنعان طلباً جديداً
            if ($draw->voidRequest()->exists()) {
                throw ValidationException::withMessages(['reason' => __('competitions.draws.void_pending')]);
            }

            $voidedBefore = CompetitionDrawVoid::query()
                ->whereIn('draw_id', CompetitionDraw::query()->where('competition_id', $draw->competition_id)->select('id'))
                ->exists();

            if ($voidedBefore) {
                $request = new CompetitionDrawVoidRequest;
                $request->draw_id = $draw->id;
                $request->reason = $reason;
                $request->requested_by = $actor->id;
                $request->requested_at = now();
                $request->save();

                activity('competitions')->performedOn($draw)->causedBy($actor)
                    ->event(ActivityEvent::DrawVoidRequested->value)
                    ->withProperties(['reason' => $reason])
                    ->log(ActivityEvent::DrawVoidRequested->value);

                return $request;
            }

            $void = new CompetitionDrawVoid;
            $void->draw_id = $draw->id;
            $void->reason = $reason;
            $void->voided_by = $actor->id;
            $void->voided_at = now();
            $void->save();

            activity('competitions')->performedOn($draw)->causedBy($actor)
                ->event(ActivityEvent::DrawVoided->value)
                ->withProperties(['reason' => $reason])
                ->log(ActivityEvent::DrawVoided->value);

            return $void;
        });
    }
}
