<?php

namespace App\Policies;

use App\Models\ArchiveItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * عناصر الأرشيف (صلاحيات archive.*)، وفوقها أدوار التصنيف: لا يطّلع أحد على عنصر في تصنيف لا يشمل دوره
 * ولو ملك الصلاحية. والمشرف العام يطّلع على الكل (ArchiveCategory::allows).
 */
class ArchiveItemPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'archive';
    }

    public function view(User $user, Model $record): bool
    {
        return $this->allowsIn($user, $record, 'view');
    }

    public function update(User $user, Model $record): bool
    {
        return $this->allowsIn($user, $record, 'update');
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->allowsIn($user, $record, 'delete');
    }

    public function restore(User $user, Model $record): bool
    {
        return $this->allowsIn($user, $record, 'delete');
    }

    private function allowsIn(User $user, Model $record, string $action): bool
    {
        /** @var ArchiveItem $record */
        return $this->allows($user, $action) && $record->category->allows($user);
    }
}
