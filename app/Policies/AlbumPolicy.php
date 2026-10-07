<?php

namespace App\Policies;

/**
 * ألبومات مكتبة الصور (صلاحيات media.*).
 */
class AlbumPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'media';
    }
}
