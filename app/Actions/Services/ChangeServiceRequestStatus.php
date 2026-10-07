<?php

namespace App\Actions\Services;

use App\Enums\ActivityEvent;
use App\Enums\OpeningKind;
use App\Enums\RoleName;
use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\ServiceRequestStatusChanged;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * نقل الطلب إلى حالة جديدة من اللوحة، بالانتقالات المسموحة في ServiceRequestStatus فقط.
 * يُسجَّل في سجل الطلب (مع الرد على المتقدم والملاحظة الداخلية) وفي سجل العمليات، ويُشعَر المتقدم.
 *
 * قبول طلب الانضمام للجمعية العمومية يمنح صاحبه الدور assembly_member في المعاملة نفسها. ولأن
 * منح الأدوار للمشرف العام وحده (docs/roles-permissions.md)، فهذا القبول له وحده.
 */
class ChangeServiceRequestStatus
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException انتقال غير مسموح من الحالة الحالية
     */
    public function handle(User $actor, ServiceRequest $request, ServiceRequestStatus $to, ?string $message = null, ?string $note = null): ServiceRequest
    {
        if (! $actor->can('update', $request)) {
            throw new AuthorizationException;
        }

        $grantsMembership = $to === ServiceRequestStatus::Accepted && $request->opening->kind === OpeningKind::Membership;

        if ($grantsMembership && ! $actor->hasRole(RoleName::SuperAdmin->value)) {
            throw new AuthorizationException(__('services.membership_accept_super_admin'));
        }

        // لا تُمنح أدوار لحساب موقوف أو أُخفيت بياناته (docs/roles-permissions.md)
        if ($grantsMembership && (! $request->user->is_active || $request->user->anonymized_at !== null)) {
            throw ValidationException::withMessages(['status' => __('services.inactive_applicant')]);
        }

        DB::transaction(function () use ($actor, $request, $to, $message, $note, $grantsMembership): void {
            // قفل السطر، فلا يُبنى انتقالان متزامنان على حالة قديمة
            /** @var ServiceRequest $locked */
            $locked = ServiceRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            if (! in_array($to, $from->staffTransitions(), true)) {
                throw ValidationException::withMessages(['status' => __('services.invalid_transition')]);
            }

            $locked->status = $to;
            $locked->save();
            $request->setRawAttributes($locked->getAttributes(), true);

            $event = $locked->events()->create([
                'actor_id' => $actor->id,
                'from_status' => $from,
                'to_status' => $to,
                'message' => filled($message) ? $message : null,
                'internal_note' => filled($note) ? $note : null,
            ]);

            if ($grantsMembership) {
                $this->grantMembership($actor, $request->user);
            }

            if ($request->user->anonymized_at === null) {
                $request->user->notify(new ServiceRequestStatusChanged($request, $event));
            }
        });

        return $request;
    }

    private function grantMembership(User $actor, User $member): void
    {
        $role = RoleName::AssemblyMember->value;

        if ($member->hasRole($role)) {
            return;
        }

        $before = $member->getRoleNames()->sort()->values()->all();
        $member->assignRole($role);

        activity('users')
            ->causedBy($actor)
            ->performedOn($member)
            ->event(ActivityEvent::RolesChanged->value)
            ->withProperties(['old' => $before, 'new' => $member->getRoleNames()->sort()->values()->all()])
            ->log(ActivityEvent::RolesChanged->value);
    }
}
