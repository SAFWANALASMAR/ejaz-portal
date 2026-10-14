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
 * اعتماد طلب إلغاء سحب بعد الإلغاء الأول في المسابقة: لمشرف عام غير طالب الإلغاء (CompetitionDrawPolicy).
 * فيُسجَّل الإلغاء باسم الطالب وسببه، ومعه المعتمد، فلا يعيد الطالب السحب بنفسه (RunDraw).
 */
class ApproveDrawVoid
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, CompetitionDraw $draw): CompetitionDrawVoid
    {
        if (! $actor->can('approveVoid', $draw)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($actor, $draw): CompetitionDrawVoid {
            $draw->competition()->lockForUpdate()->first();

            if (CompetitionDrawVoid::query()->where('draw_id', $draw->id)->exists()) {
                throw ValidationException::withMessages(['reason' => __('competitions.draws.already_void')]);
            }

            $request = CompetitionDrawVoidRequest::query()->where('draw_id', $draw->id)->firstOrFail();

            $void = new CompetitionDrawVoid;
            $void->draw_id = $draw->id;
            $void->reason = $request->reason;
            $void->voided_by = $request->requested_by;
            $void->approved_by = $actor->id;
            $void->voided_at = now();
            $void->save();

            activity('competitions')->performedOn($draw)->causedBy($actor)
                ->event(ActivityEvent::DrawVoidApproved->value)
                ->withProperties(['reason' => $request->reason, 'requested_by' => $request->requested_by])
                ->log(ActivityEvent::DrawVoidApproved->value);

            return $void;
        });
    }
}
