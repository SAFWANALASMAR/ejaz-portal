<?php

namespace App\Actions\Assembly;

use App\Enums\ActivityEvent;
use App\Enums\MembershipStatus;
use App\Enums\RoleName;
use App\Models\AssemblyMembership;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * حفظ عضوية في سجل الجمعية العمومية، وهو وحده يمنح دور عضو الجمعية العمومية ويسحبه في المعاملة نفسها:
 * الفعّالة تمنحه، والموقوفة والمنتهية تسحبه. وللمشرف العام وحده (صلاحيتان لا تُربطان بدور، قرار صفوان)،
 * ويُسجَّل الحفظ وتغيير الأدوار. ولا تُمنح عضوية فعّالة لحساب موقوف أو أُخفيت بياناته.
 */
class SaveAssemblyMembership
{
    /**
     * @param  array<string, mixed>  $data  user_id (عند الإنشاء)، وstatus، وjoined_on
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, AssemblyMembership $membership, array $data): AssemblyMembership
    {
        $allowed = $membership->exists ? $actor->can('update', $membership) : $actor->can('create', AssemblyMembership::class);

        if (! $allowed) {
            throw new AuthorizationException;
        }

        $validated = Validator::make($data, [
            'user_id' => $membership->exists ? ['prohibited'] : ['required', 'integer', Rule::unique('assembly_memberships', 'user_id')],
            'status' => ['required', Rule::enum(MembershipStatus::class)],
            'joined_on' => ['nullable', 'date'],
        ], [
            'user_id.unique' => __('assembly.validation.already_member'),
        ])->validate();

        if (! $membership->exists) {
            $membership->user()->associate(User::query()->findOrFail($validated['user_id']));
        }

        $membership->status = MembershipStatus::from($validated['status']);

        if (array_key_exists('joined_on', $validated)) {
            $membership->joined_on = filled($validated['joined_on']) ? Carbon::parse($validated['joined_on']) : null;
        }

        return $this->save($actor, $membership);
    }

    /**
     * قبول طلب الانضمام (ChangeServiceRequestStatus، والمشرف العام وحده يقبله): عضوية فعّالة بتاريخ القبول
     * ورقم الطلب، أو إعادة تفعيل عضوية سابقة.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function acceptRequest(User $actor, ServiceRequest $request): AssemblyMembership
    {
        $membership = AssemblyMembership::query()->firstOrNew(['user_id' => $request->user_id]);

        if (! ($membership->exists ? $actor->can('update', $membership) : $actor->can('create', AssemblyMembership::class))) {
            throw new AuthorizationException;
        }

        if ($membership->status !== MembershipStatus::Active) {
            $membership->joined_on = now()->startOfDay();
        }

        $membership->status = MembershipStatus::Active;
        $membership->service_request_id = $request->id;

        return $this->save($actor, $membership);
    }

    /**
     * @throws ValidationException
     */
    private function save(User $actor, AssemblyMembership $membership): AssemblyMembership
    {
        /** @var User $member */
        $member = $membership->user;
        $active = $membership->status === MembershipStatus::Active;

        if ($active && (! $member->is_active || $member->anonymized_at !== null)) {
            throw ValidationException::withMessages(['user_id' => __('assembly.validation.inactive_user')]);
        }

        DB::transaction(function () use ($actor, $membership, $member, $active): void {
            $membership->save();
            $this->syncRole($actor, $member, $active);
        });

        return $membership;
    }

    private function syncRole(User $actor, User $member, bool $active): void
    {
        $role = RoleName::AssemblyMember->value;

        if ($member->hasRole($role) === $active) {
            return;
        }

        $before = $member->getRoleNames()->sort()->values()->all();
        $active ? $member->assignRole($role) : $member->removeRole($role);

        activity('users')
            ->causedBy($actor)
            ->performedOn($member)
            ->event(ActivityEvent::RolesChanged->value)
            ->withProperties(['old' => $before, 'new' => $member->getRoleNames()->sort()->values()->all()])
            ->log(ActivityEvent::RolesChanged->value);
    }
}
