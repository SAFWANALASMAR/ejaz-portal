<?php

namespace App\Actions\Users;

use App\Enums\ActivityEvent;
use App\Enums\RoleName;
use App\Models\User;
use App\Notifications\SuperAdminRoleChanged;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * منح الأدوار وسحبها. للمشرف العام وحده (UserPolicy::assignRoles)، ويُسجَّل كل تغيير
 * بالأدوار قبل وبعد (docs/roles-permissions.md). ولا يسحب المشرف العام دوره من نفسه.
 *
 * منح دور المشرف العام أو سحبه يُبلَّغ به كل من كان مشرفاً عاماً قبل التغيير أو بعده،
 * ومنهم من سُحب دوره، فلا يخفى السحب على صاحبه ولو نفّذه حساب مخترَق.
 *
 * ودور عضو الجمعية العمومية لا يُمنح هنا ولا يُسحب: يتبع العضوية في سجل الأعضاء (SaveAssemblyMembership)،
 * فيبقى كما هو.
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
        $assemblyMember = RoleName::AssemblyMember->value;
        $before = $user->getRoleNames()->sort()->values()->all();
        $valid = array_values(array_diff(array_intersect($roles, RoleName::values(RoleName::cases())), [$assemblyMember]));
        $valid = $valid === [] ? [RoleName::Member->value] : $valid;

        if (in_array($assemblyMember, $before, true)) {
            $valid[] = $assemblyMember;
        }

        if ($actor->is($user) && in_array($super, $before, true) && ! in_array($super, $valid, true)) {
            throw new AuthorizationException(__('users.cannot_demote_self'));
        }

        $superChanges = in_array($super, $before, true) !== in_array($super, $valid, true);
        $recipients = $superChanges ? self::activeSuperAdmins() : new Collection;

        DB::transaction(function () use ($actor, $user, $valid, $before): void {
            $user->syncRoles($valid);

            $after = $user->getRoleNames()->sort()->values()->all();

            if ($before !== $after) {
                activity('users')
                    ->causedBy($actor)
                    ->performedOn($user)
                    ->event(ActivityEvent::RolesChanged->value)
                    ->withProperties(['old' => $before, 'new' => $after])
                    ->log(ActivityEvent::RolesChanged->value);
            }
        });

        // الطابور ينتظر اعتماد المعاملة (after_commit في config/queue.php)، ولو استُدعي
        // الإجراء داخل معاملة أكبر كإنشاء حساب موظف
        if ($superChanges) {
            Notification::send(
                $recipients->merge(self::activeSuperAdmins()),
                new SuperAdminRoleChanged($user, granted: in_array($super, $valid, true), by: $actor),
            );
        }
    }

    /**
     * @return Collection<int, User>
     */
    private static function activeSuperAdmins(): Collection
    {
        return User::role(RoleName::SuperAdmin->value)->where('is_active', true)->get();
    }
}
