<?php

namespace App\Policies;

/**
 * عناصر المكتبة (صلاحيات library.*). الحذف ناعم ويُستعاد، والحذف النهائي ممنوع على الجميع
 * (ModulePolicy).
 */
class LibraryItemPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'library';
    }
}
