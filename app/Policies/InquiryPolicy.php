<?php

namespace App\Policies;

use App\Models\User;

/**
 * رسائل الاستفسارات والاقتراحات (صلاحيات requests.* — السكرتير). تصل من الموقع العام، فلا
 * إنشاء لها من اللوحة. والحذف ناعم، والحذف النهائي ممنوع على الجميع (ModulePolicy).
 */
class InquiryPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'requests';
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function assign(User $user): bool
    {
        return $this->allows($user, 'assign');
    }
}
