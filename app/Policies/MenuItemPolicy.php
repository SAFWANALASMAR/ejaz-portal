<?php

namespace App\Policies;

use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class MenuItemPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'menu';
    }

    /**
     * العنصر الذي تحته عناصر فرعية لا يُحذف، حتى لا تُحذف فروعه بصمت.
     * تُنقل الفروع أو تُحذف أولاً. والمنع صريح فيسري على المشرف العام أيضاً.
     */
    public function delete(User $user, Model $record): bool
    {
        if ($record instanceof MenuItem && $record->children()->exists()) {
            return false;
        }

        return parent::delete($user, $record);
    }
}
