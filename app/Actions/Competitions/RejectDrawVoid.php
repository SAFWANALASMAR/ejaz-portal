<?php

namespace App\Actions\Competitions;

use App\Enums\ActivityEvent;
use App\Enums\DrawVoidRequestStatus;
use App\Models\CompetitionDraw;
use App\Models\CompetitionDrawVoidRequest;
use App\Models\User;
use App\Notifications\DrawVoidRequestClosed;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * رفض طلب إلغاء سحب: لمن يعتمده، أي مشرف عام غير طالبه (CompetitionDrawPolicy::rejectVoid)، بسبب إلزامي.
 * فيبقى السحب معتمداً، ويُسجَّل الرفض وسببه، ويُبلَّغ الطالب. ويُتاح بعده طلب جديد يحتاج قراراً جديداً.
 */
class RejectDrawVoid
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, CompetitionDraw $draw, string $reason): CompetitionDrawVoidRequest
    {
        if (! $actor->can('rejectVoid', $draw)) {
            throw new AuthorizationException;
        }

        $reason = trim($reason);

        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => __('competitions.draws.reject_reason_rule')]);
        }

        return DB::transaction(function () use ($actor, $draw, $reason): CompetitionDrawVoidRequest {
            // القفل نفسه في الإلغاء والاعتماد، فلا يتزامن رفض واعتماد للطلب نفسه
            $competition = $draw->competition()->lockForUpdate()->firstOrFail();

            $request = $draw->voidRequest()->with('requester')->first()
                ?? throw ValidationException::withMessages(['reason' => __('competitions.draws.void_request_closed')]);

            if ($request->hasLapsed()) {
                throw ValidationException::withMessages(['reason' => __('competitions.draws.void_request_lapsed')]);
            }

            $request->status = DrawVoidRequestStatus::Rejected;
            $request->decided_by = $actor->id;
            $request->decision_reason = $reason;
            $request->decided_at = now();
            $request->save();

            activity('competitions')->performedOn($draw)->causedBy($actor)
                ->event(ActivityEvent::DrawVoidRejected->value)
                ->withProperties(['reason' => $reason, 'request_reason' => $request->reason, 'requested_by' => $request->requested_by])
                ->log(ActivityEvent::DrawVoidRejected->value);

            if ($request->requester?->is_active) {
                $request->requester->notify(new DrawVoidRequestClosed($competition, DrawVoidRequestStatus::Rejected, $reason));
            }

            return $request;
        });
    }
}
