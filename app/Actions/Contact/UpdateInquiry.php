<?php

namespace App\Actions\Contact;

use App\Enums\InquiryStatus;
use App\Enums\RoleName;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * معالجة الاستفسار في اللوحة: الحالة والملاحظة الداخلية بصلاحية requests.update، والإسناد بصلاحية
 * requests.assign إلى موظف نشط له الاطلاع على الطلبات. وكل تغيير في سجل العمليات (LogsActivity).
 */
class UpdateInquiry
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, Inquiry $inquiry, array $data): Inquiry
    {
        if (! $actor->can('update', $inquiry)) {
            throw new AuthorizationException;
        }

        $data = Arr::only($data, ['status', 'assigned_to', 'internal_note']);

        if (array_key_exists('status', $data) && ! $data['status'] instanceof InquiryStatus && InquiryStatus::tryFrom((string) $data['status']) === null) {
            throw ValidationException::withMessages(['status' => __('inquiries.validation.status')]);
        }

        $inquiry->fill($data);

        if ($inquiry->isDirty('assigned_to')) {
            if (! $actor->can('assign', $inquiry)) {
                throw new AuthorizationException;
            }

            if ($inquiry->assigned_to !== null && ! array_key_exists((int) $inquiry->assigned_to, self::assignees())) {
                throw ValidationException::withMessages(['assigned_to' => __('inquiries.validation.assignee')]);
            }
        }

        $inquiry->save();

        return $inquiry;
    }

    /**
     * الموظفون النشطون الذين يُسند إليهم: من له الاطلاع على الطلبات بدوره، والمشرف العام.
     *
     * @return array<int, string>
     */
    public static function assignees(): array
    {
        return User::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->permission('requests.view')->orWhereHas('roles', fn ($roles) => $roles->where('name', RoleName::SuperAdmin->value)))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
