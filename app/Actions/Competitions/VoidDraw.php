<?php

namespace App\Actions\Competitions;

use App\Enums\ActivityEvent;
use App\Enums\RoleName;
use App\Models\CompetitionDraw;
use App\Models\CompetitionDrawVoid;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * إلغاء سحب معتمد: للمشرف العام وحده، بسبب إلزامي يُسجَّل. فيُتاح سحب جديد، ويبقى الملغى ظاهراً
 * معلَّماً بسببه، فلا يُختار سحب من بين سحوبات خفية.
 */
class VoidDraw
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, CompetitionDraw $draw, string $reason): CompetitionDrawVoid
    {
        if (! $actor->hasRole(RoleName::SuperAdmin->value)) {
            throw new AuthorizationException;
        }

        $reason = trim($reason);

        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => __('competitions.draws.void_reason_rule')]);
        }

        return DB::transaction(function () use ($actor, $draw, $reason): CompetitionDrawVoid {
            $draw->competition()->lockForUpdate()->first();

            if (CompetitionDrawVoid::query()->where('draw_id', $draw->id)->exists()) {
                throw ValidationException::withMessages(['reason' => __('competitions.draws.already_void')]);
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
