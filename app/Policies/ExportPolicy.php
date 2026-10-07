<?php

namespace App\Policies;

use App\Models\User;
use Filament\Actions\Exports\Models\Export;

/**
 * ملف التصدير ينزّله من طلبه وحده، وما دام يملك صلاحية التصدير. فمن سُحبت صلاحيته لا ينزّل ما صدّره
 * قبلها (فيه بيانات شخصية).
 */
class ExportPolicy
{
    public function view(User $user, Export $export): bool
    {
        return $export->user_id === $user->id && $user->can('submissions.export');
    }
}
