<?php

namespace App\Policies;

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
