<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * سياسة موحدة لوحدات المحتوى: كل عملية تقابلها صلاحية <الوحدة>.<العملية>
 * (config/permissions.php). كل نموذج له سياسة ترث هذه وتحدد وحدتها،
 * وتتجاوز ما يختلف فيها. والمشرف العام يملك كل شيء عبر Gate::before.
 */
abstract class ModulePolicy
{
    abstract protected function module(): string;

    protected function allows(User $user, string $action): bool
    {
        return $user->can($this->module().'.'.$action);
    }

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view');
    }

    public function view(User $user, Model $record): bool
    {
        return $this->allows($user, 'view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create');
    }

    public function update(User $user, Model $record): bool
    {
        return $this->allows($user, 'update');
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->allows($user, 'delete');
    }

    public function deleteAny(User $user): bool
    {
        return $this->allows($user, 'delete');
    }

    public function reorder(User $user): bool
    {
        return $this->allows($user, 'update');
    }

    public function restore(User $user, Model $record): bool
    {
        return $this->allows($user, 'delete');
    }

    public function restoreAny(User $user): bool
    {
        return $this->allows($user, 'delete');
    }

    /**
     * الحذف النهائي للمشرف العام وحده (عبر Gate::before).
     */
    public function forceDelete(User $user, Model $record): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }
}
