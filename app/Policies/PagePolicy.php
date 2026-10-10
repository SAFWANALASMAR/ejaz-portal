<?php

namespace App\Policies;

use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PagePolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'pages';
    }

    /**
     * صفحة «عن الجمعية» لا تُحذف، لأن رابط القائمة مبني عليها. والمنع صريح فيسري على المشرف العام.
     */
    public function delete(User $user, Model $record): bool
    {
        if ($record instanceof Page && $record->slug === Page::ABOUT) {
            return false;
        }

        return parent::delete($user, $record);
    }
}
