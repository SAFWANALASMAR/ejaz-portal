<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * أجزاء مسابقة الطلاب وقوائمها (المراحل، والحقول، والنصوص، وبنك الأسئلة، والفئات، والمناطق): يطّلع عليها من
 * يطّلع على المسابقات، ويضيفها ويعدّلها ويحذفها من يعدّل المسابقات (competitions.update). والأقفال بعد أول
 * محاولة في الإجراءات (docs/plans/student-competition.md).
 */
abstract class CompetitionPartPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'competitions';
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'update');
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->allows($user, 'update');
    }

    public function deleteAny(User $user): bool
    {
        return $this->allows($user, 'update');
    }

    public function restore(User $user, Model $record): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }
}
