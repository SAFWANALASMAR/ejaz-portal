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
     * صفحتا «عن الجمعية» و«سياسة الخصوصية» لا تُحذفان، لأن رابطيهما مبنيان على معرّفيهما (Page::LOCKED_SLUGS).
     * والمنع صريح فيسري على المشرف العام.
     */
    public function delete(User $user, Model $record): bool
    {
        if ($record instanceof Page && in_array($record->slug, Page::LOCKED_SLUGS, true)) {
            return false;
        }

        return parent::delete($user, $record);
    }
}
