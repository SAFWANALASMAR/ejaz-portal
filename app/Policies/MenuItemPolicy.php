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
     * تُنقل الفروع أو تُحذف أولاً. والمنع صريح فيسري على المشرف العام أيضاً،
     * وقاعدة البيانات ترفضه كذلك (restrictOnDelete).
     */
    public function delete(User $user, Model $record): bool
    {
        if ($record instanceof MenuItem && self::hasChildren($record)) {
            return false;
        }

        return parent::delete($user, $record);
    }

    /**
     * جدول القائمة يحمّل عدد الفروع مسبقاً (withCount)، فلا استعلام لكل صف.
     */
    private static function hasChildren(MenuItem $item): bool
    {
        return $item->hasAttribute('children_count')
            ? (int) $item->getAttribute('children_count') > 0
            : $item->children()->exists();
    }
}
