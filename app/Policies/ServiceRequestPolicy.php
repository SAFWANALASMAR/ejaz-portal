<?php

namespace App\Policies;

use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * الطلبات الإلكترونية (صلاحيات requests.*).
 *
 * - هذه السياسة للوحة الإدارة: الموظف يرى الطلبات المرسلة ويعالجها بصلاحيته. والمسودة لا يراها
 *   غير صاحبها، ولا المشرف العام (منع صريح لا يغيّره Gate::after).
 * - صاحب الطلب يتابعه من «طلباتي» لا من اللوحة، فلا يرى فيها ملاحظاته الداخلية ولو كان له دور
 *   إداري، ولا يعالج طلبه بنفسه. ويعدّله ويرسله ما دام مسودة أو بحاجة لاستكمال (edit).
 */
class ServiceRequestPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'requests';
    }

    public function view(User $user, Model $record): bool
    {
        /** @var ServiceRequest $record */
        return $record->status !== ServiceRequestStatus::Draft && $this->allows($user, 'view');
    }

    /**
     * المتقدم يعدّل طلبه.
     */
    public function edit(User $user, ServiceRequest $record): bool
    {
        return $record->user_id === $user->id && $record->status->editableByApplicant();
    }

    /**
     * الموظف يغيّر الحالة. والمسودة ليست له، ولا طلبه هو.
     */
    public function update(User $user, Model $record): bool
    {
        /** @var ServiceRequest $record */
        return $record->status !== ServiceRequestStatus::Draft && $record->user_id !== $user->id && $this->allows($user, 'update');
    }

    public function assign(User $user, ServiceRequest $record): bool
    {
        return $record->status !== ServiceRequestStatus::Draft && $record->user_id !== $user->id && $this->allows($user, 'assign');
    }

    public function export(User $user): bool
    {
        return $this->allows($user, 'export');
    }

    /**
     * لا ينشئ الموظف طلباً من اللوحة؛ الطلب يقدمه صاحبه من الموقع.
     */
    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, Model $record): bool
    {
        /** @var ServiceRequest $record */
        return $record->status !== ServiceRequestStatus::Draft && $this->allows($user, 'delete');
    }
}
