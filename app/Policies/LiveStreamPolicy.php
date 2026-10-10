<?php

namespace App\Policies;

/**
 * البث المباشر في اللوحة (صلاحيات livestreams.*): محرر المحتوى يديره، وعضو المجلس يطّلع.
 * والحذف ناعم، والحذف النهائي ممنوع على الجميع (ModulePolicy).
 */
class LiveStreamPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'livestreams';
    }
}
