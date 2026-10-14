<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\CompetitionDraw;
use App\Models\User;

/**
 * سجل السحب: يطّلع عليه من يطّلع على المسابقات (ولجنتها)، ويُنفّذه من له competitions.draw عبر
 * RunDraw. ولا تعديل ولا حذف لأحد، ولا المشرف العام (منع صريح لا يغيّره Gate::after).
 */
class CompetitionDrawPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('competitions.view');
    }

    public function view(User $user, CompetitionDraw $draw): bool
    {
        return $user->can('competitions.view');
    }

    public function create(User $user): bool
    {
        return $user->can('competitions.draw');
    }

    /**
     * إلغاء سحب معتمد بسبب: للمشرف العام وحده (VoidDraw).
     */
    public function void(User $user, CompetitionDraw $draw): bool
    {
        return $user->hasRole(RoleName::SuperAdmin->value);
    }

    /**
     * اعتماد طلب إلغاء بعد الإلغاء الأول في المسابقة (ApproveDrawVoid): لمشرف عام غير طالب الإلغاء.
     * نتيجة صريحة، فلا يجعلها Gate::after صحيحة لطالب الإلغاء.
     */
    public function approveVoid(User $user, CompetitionDraw $draw): bool
    {
        $request = $draw->voidRequest;

        return $request !== null
            && $request->requested_by !== $user->id
            && $user->hasRole(RoleName::SuperAdmin->value);
    }

    public function update(User $user, CompetitionDraw $draw): bool
    {
        return false;
    }

    public function delete(User $user, CompetitionDraw $draw): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, CompetitionDraw $draw): bool
    {
        return false;
    }
}
