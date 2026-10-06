<?php

namespace App\Actions\Users;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * منح الأدوار وسحبها. للمشرف العام وحده (users.assign-roles)، ويُسجَّل كل تغيير
 * بالأدوار قبل وبعد (docs/roles-permissions.md).
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

        $valid = array_values(array_intersect($roles, array_map(fn (RoleName $role) => $role->value, RoleName::cases())));
        $before = $user->getRoleNames()->sort()->values()->all();

        $user->syncRoles($valid === [] ? [RoleName::Member->value] : $valid);

        $after = $user->getRoleNames()->sort()->values()->all();

        if ($before !== $after) {
            activity('users')
                ->causedBy($actor)
                ->performedOn($user)
                ->event('roles_changed')
                ->withProperties(['old' => $before, 'new' => $after])
                ->log('roles_changed');
        }
    }
}
