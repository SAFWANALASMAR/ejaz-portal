<?php

namespace App\Actions\Users;

use App\Enums\RoleName;
use App\Models\User;
use App\Notifications\SuperAdminRoleChanged;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * منح الأدوار وسحبها. للمشرف العام وحده (UserPolicy::assignRoles)، ويُسجَّل كل تغيير
 * بالأدوار قبل وبعد (docs/roles-permissions.md). ولا يسحب المشرف العام دوره من نفسه،
 * ومنح دور المشرف العام أو سحبه يُبلَّغ به كل المشرفين العامين.
 */
class ChangeUserRoles
{
    /**
     * @param  list<string>  $roles
     *
     * @throws AuthorizationException
     */
    public function handle(User $actor, User $user, array $roles): void
    {
        if (! $actor->can('assignRoles', $user)) {
            throw new AuthorizationException;
        }

        $super = RoleName::SuperAdmin->value;
        $valid = array_values(array_intersect($roles, RoleName::values(RoleName::cases())));
        $valid = $valid === [] ? [RoleName::Member->value] : $valid;
        $before = $user->getRoleNames()->sort()->values()->all();

        if ($actor->is($user) && in_array($super, $before, true) && ! in_array($super, $valid, true)) {
            throw new AuthorizationException(__('users.cannot_demote_self'));
        }

        DB::transaction(fn () => $user->syncRoles($valid));

        $after = $user->getRoleNames()->sort()->values()->all();

        if ($before === $after) {
            return;
        }

        activity('users')
            ->causedBy($actor)
            ->performedOn($user)
            ->event('roles_changed')
            ->withProperties(['old' => $before, 'new' => $after])
            ->log('roles_changed');

        if (in_array($super, $before, true) !== in_array($super, $after, true)) {
            Notification::send(
                User::role($super)->where('is_active', true)->get(),
                new SuperAdminRoleChanged($user, granted: in_array($super, $after, true), by: $actor),
            );
        }
    }
}
