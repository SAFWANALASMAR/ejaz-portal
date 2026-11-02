<?php

namespace App\Policies;

use App\Models\Page;
use App\Models\User;
use App\Support\EditablePages;
use Illuminate\Database\Eloquent\Model;

class PagePolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'pages';
    }

    /**
     * صفحات الموقع الثابتة (EditablePages، ومنها «عن الجمعية») تُعدَّل من «محرر الصفحات» بصلاحية pages.edit_all وحدها
     * (قرار صفوان، 31 أكتوبر 2026)، لا بصلاحية الصفحات العامة. والنتيجة صريحة فلا يمنحها Gate::after للمشرف العام.
     */
    public function update(User $user, Model $record): bool
    {
        if ($record instanceof Page && EditablePages::isEditable($record)) {
            return $user->can('pages.edit_all');
        }

        return parent::update($user, $record);
    }

    /**
     * صفحتا «عن الجمعية» و«سياسة الخصوصية» لا تُحذفان، لأن رابطيهما مبنيان على معرّفيهما (Page::LOCKED_SLUGS)، ولا صفحات
     * الموقع الثابتة لأن قوالبها تقرؤها. والمنع صريح فيسري على المشرف العام.
     */
    public function delete(User $user, Model $record): bool
    {
        if ($record instanceof Page && (in_array($record->slug, Page::LOCKED_SLUGS, true) || EditablePages::isEditable($record))) {
            return false;
        }

        return parent::delete($user, $record);
    }
}
