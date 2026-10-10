<?php

namespace App\Policies;

use App\Enums\AssemblyFileAudience;
use App\Models\AssemblyFile;
use App\Models\User;
use App\Support\AssemblyArea;

/**
 * مكتبة ملفات الأعضاء (صلاحيات assembly_files.*). في اللوحة يديرها السكرتير ويطّلع عليها المجلس، وفي
 * الموقع يرى الملف من يصل إلى منطقة الجمعية العمومية، وملف المجلس مجلسُ الإدارة وحده.
 */
class AssemblyFilePolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'assembly_files';
    }

    public function download(User $user, AssemblyFile $file): bool
    {
        return $file->audience === AssemblyFileAudience::Members ? AssemblyArea::allows($user) : AssemblyArea::board($user);
    }
}
