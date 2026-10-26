<?php

namespace App\Actions\Services;

use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * إسناد الطلب إلى موظف يملك معالجته، أو إلغاء الإسناد. يُسجَّل في سجل العمليات (LogsActivity).
 */
class AssignServiceRequest
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException المسند إليه لا يملك معالجة الطلب
     */
    public function handle(User $actor, ServiceRequest $request, ?User $assignee): ServiceRequest
    {
        if (! $actor->can('assign', $request)) {
            throw new AuthorizationException;
        }

        if ($assignee !== null && (! $assignee->is_active || ! $assignee->can('update', $request))) {
            throw ValidationException::withMessages(['assigned_to' => __('services.invalid_assignee')]);
        }

        $request->assigned_to = $assignee?->id;
        $request->save();

        return $request;
    }

    /**
     * من يُسند إليهم: الحسابات النشطة التي تملك معالجة الطلبات.
     *
     * @return array<int, string>
     */
    public static function candidates(): array
    {
        return User::query()
            ->where('is_active', true)
            ->whereNull('anonymized_at')
            ->permission('requests.update')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
