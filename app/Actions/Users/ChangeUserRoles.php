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
 * منح الأدوار وسحبها (UserPolicy::assignRoles): لمديري النظام (RoleName::assignableBy). ويُسجَّل كل تغيير بالأدوار قبل
 * وبعد (docs/roles-permissions.md). ولا يسحب مدير النظام دوره الإداري من نفسه، فلا تبقى البوابة بلا من يديرها.
 *
 * منح دور رقابي (المشرف العام أو المدير التنفيذي أو المسؤول التقني) أو سحبه يُبلَّغ به كل من كان منهم قبل التغيير أو بعده،
 * ومنهم من سُحب دوره، فلا يخفى السحب على صاحبه ولو نفّذه حساب مخترَق.
 *
 * ودور عضو الجمعية العمومية لا يُمنح هنا ولا يُسحب: يتبع العضوية في سجل الأعضاء (SaveAssemblyMembership)،
 * فيبقى كما هو.
 */
class ChangeUserRoles
{
    /**
     * @param  list<string>  $roles
     * @param  bool  $newAccount  حساب أنشأه المنفّذ للتو في CreateStaffUser ولم يأخذ دوراً بعد: يكفي فيه أن
     *                            ينشئ الحسابات ويمنح أدواراً، فلا تحتاج السياسة استثناءً للحساب بلا أدوار
     *
     * @throws AuthorizationException
     */
    public function handle(User $actor, User $user, array $roles, bool $newAccount = false): void
    {
        $allowed = $newAccount
            ? $user->wasRecentlyCreated && $user->roles()->doesntExist() && $actor->can('create', User::class) && $actor->can('assignRoles', User::class)
            : $actor->can('assignRoles', $user);

        if (! $allowed) {
            throw new AuthorizationException;
        }

        $administrators = RoleName::values(RoleName::administrators());
        $assemblyMember = RoleName::AssemblyMember->value;
        $before = $user->getRoleNames()->sort()->values()->all();
        $assignable = RoleName::values(RoleName::assignableBy($actor));
        $requested = array_values(array_unique(array_diff(array_intersect($roles, RoleName::values(RoleName::cases())), [$assemblyMember])));

        // دور لا يمنحه المنفّذ (المشرف العام أو عضو المجلس للمدير التنفيذي مثلاً) مرفوض صراحة،
        // إلا ما يحمله الحساب أصلاً فيبقى كما هو
        if (array_diff($requested, $assignable, $before) !== []) {
            throw new AuthorizationException;
        }

        $valid = array_values(array_intersect($requested, $assignable));
        $valid = $valid === [] ? [RoleName::Member->value] : $valid;

        // ما لا يمنحه المنفّذ ولا يسحبه يبقى كما هو، ومنه عضوية الجمعية العمومية
        $valid = array_values(array_unique([...$valid, ...array_diff($before, $assignable)]));

        if ($actor->is($user) && array_intersect($administrators, $before) !== [] && array_intersect($administrators, $valid) === []) {
            throw new AuthorizationException(__('users.cannot_demote_self'));
        }

        // كل دور رقابي تغيّر (المشرف العام أو المدير التنفيذي أو المسؤول التقني)، لا أولها وحده (مراجعة المشرف المبكرة، ١٠)
        $watched = array_values(array_filter(RoleName::values(RoleName::overseers()),
            fn (string $role): bool => in_array($role, $before, true) !== in_array($role, $valid, true)));
        $recipients = $watched !== [] ? self::activeOverseers() : new Collection;

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
        self::notifyOverseers($recipients, $user, $watched, $valid, $actor);
    }

    /**
     * يُبلَّغ مَن كان رقيباً قبل التغيير ومن صار بعده، بتنبيه لكل دور تغيّر. والمنفّذ null لأمر الطرفية (ejaz:create-admin).
     *
     * @param  Collection<int, User>  $before
     * @param  list<string>  $watched
     * @param  list<string>  $after
     */
    public static function notifyOverseers(Collection $before, User $user, array $watched, array $after, ?User $actor): void
    {
        if ($watched === []) {
            return;
        }

        $recipients = $before->merge(self::activeOverseers())->unique('id');

        foreach ($watched as $role) {
            Notification::send($recipients, new SuperAdminRoleChanged($user, granted: in_array($role, $after, true), by: $actor, role: RoleName::from($role)));
        }
    }

    /**
     * @return Collection<int, User>
     */
    public static function activeOverseers(): Collection
    {
        return User::role(RoleName::values(RoleName::overseers()))->where('is_active', true)->get();
    }
}
