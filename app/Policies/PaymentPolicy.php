<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

/**
 * المدفوعات: صاحب العملية يرى عمليته، والإدارة بـ payments.view، والاسترداد بـ payments.refund
 * (المشرف العام). ولا إنشاء ولا تعديل ولا حذف من اللوحة لأحد، ولا المشرف العام: سجل مالي
 * (منع صريح لا يغيّره Gate::after).
 */
class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('payments.view');
    }

    public function view(User $user, Payment $payment): bool
    {
        return $payment->user_id === $user->id || $user->can('payments.view');
    }

    public function refund(User $user, Payment $payment): bool
    {
        return $user->can('payments.refund');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Payment $payment): bool
    {
        return false;
    }

    public function delete(User $user, Payment $payment): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, Payment $payment): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }
}
