<?php

namespace App\Policies;

use App\Models\Program;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * البرامج ومسجلوها (صلاحيات programs.*): الاطلاع على المسجلين مع البرنامج، وتسجيل حضورهم
 * وتصديرهم بصلاحيتين مستقلتين. الحذف ناعم، والبرنامج المحذوف لا يطّلع عليه إلا من يحذف ويستعيد.
 */
class ProgramPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'programs';
    }

    public function view(User $user, Model $record): bool
    {
        /** @var Program $record */
        return $this->allows($user, $record->trashed() ? 'delete' : 'view');
    }

    public function export(User $user): bool
    {
        return $this->allows($user, 'export');
    }

    /**
     * تسجيل حضور المسجلين بصلاحية مستقلة (programs.attend)، فيسجله الموظف دون أن يعدّل البرنامج.
     * ولا حضور في برنامج محذوف (فلا تصدر عنه شهادة).
     */
    public function recordAttendance(User $user, Program $program): bool
    {
        return ! $program->trashed() && $this->allows($user, 'attend');
    }
}
