<?php

namespace App\Policies;

/**
 * الإعلانات (صلاحيات announcements.*): المدير التنفيذي والمسؤول التقني ومحرر المحتوى.
 */
class AnnouncementPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'announcements';
    }
}
