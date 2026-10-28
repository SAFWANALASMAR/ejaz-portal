<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\CompetitionDraw;
use App\Models\User;

/**
 * سجل السحب: يطّلع عليه من يطّلع على المسابقات (ولجنتها)، ويُنفّذه من له competitions.draw عبر
 * RunDraw. ولا تعديل ولا حذف لأحد، ولا المسؤول التقني (منع صريح لا يغيّره Gate::after).
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
     * إلغاء سحب معتمد بسبب (VoidDraw): للمدير التنفيذي والمسؤول التقني (كان للمشرف العام وحده، وصار اطلاعاً فقط —
     * المدير التنفيذي عبر صفوان، 25 أكتوبر 2026).
     */
    public function void(User $user, CompetitionDraw $draw): bool
    {
        return self::voids($user);
    }

    /**
     * اعتماد طلب إلغاء بعد الإلغاء الأول في المسابقة (ApproveDrawVoid): لمن يلغي السحب غير طالب الإلغاء.
     * نتيجة صريحة، فلا يجعلها Gate::after صحيحة لطالب الإلغاء. والطلب الذي مضت مدته لا يُعتمد ولا يُرفض.
     */
    public function approveVoid(User $user, CompetitionDraw $draw): bool
    {
        $request = $draw->openVoidRequest();

        return $request !== null
            && $request->requested_by !== $user->id
            && self::voids($user);
    }

    private static function voids(User $user): bool
    {
        return RoleName::isAdministrator($user);
    }

    /**
     * رفض طلب الإلغاء بسبب (RejectDrawVoid): لمن يعتمده، فلا يرفض الطالب طلبه.
     */
    public function rejectVoid(User $user, CompetitionDraw $draw): bool
    {
        return $this->approveVoid($user, $draw);
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
