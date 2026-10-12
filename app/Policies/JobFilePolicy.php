<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\JobFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ملفات الوظائف (requirements/11). إدارة سجلات الملفات بصلاحيات job_files.* (المدير التنفيذي)، والاطلاع على
 * كل الملفات بـ job_files.view. وما سواه بالدور في الملف: المسؤول والمشاركون يطّلعون، والمسؤول يحدد
 * المشاركين. ولا يرى غيرهم الملف.
 */
class JobFilePolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'job_files';
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(RoleName::values(RoleName::staff())) || $this->allows($user, 'view');
    }

    public function view(User $user, Model $record): bool
    {
        /** @var JobFile $record */
        return $record->isResponsible($user) || $record->isParticipant($user) || $this->allows($user, 'view');
    }

    /**
     * المشاركون وإذن إضافتهم: يحددهم المسؤول عن الملف (ومنهم «أصحاب الاختصاص» و«المختص» في المصفوفة).
     */
    public function manageParticipants(User $user, JobFile $record): bool
    {
        return $record->isResponsible($user) || $this->allows($user, 'update');
    }

    /**
     * الملف الذي فيه مستندات لا يُحذف.
     */
    public function delete(User $user, Model $record): bool
    {
        /** @var JobFile $record */
        return ! $record->documents()->withTrashed()->exists() && $this->allows($user, 'delete');
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
