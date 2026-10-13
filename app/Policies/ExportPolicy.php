<?php

namespace App\Policies;

use App\Filament\Exports\ProgramRegistrationExporter;
use App\Filament\Exports\ServiceRequestExporter;
use App\Filament\Exports\SubmissionExporter;
use App\Models\User;
use Filament\Actions\Exports\Models\Export;

/**
 * ملف التصدير ينزّله من طلبه وحده، وما دام يملك صلاحية التصدير. فمن سُحبت صلاحيته لا ينزّل ما صدّره
 * قبلها (فيه بيانات شخصية). ومسار التنزيل من Filament خارج وسطاء اللوحة، فيُشترط التحقق الثنائي هنا.
 */
class ExportPolicy
{
    public function view(User $user, Export $export): bool
    {
        // لكل مصدِّر صلاحيته، وما لم يُذكر هنا لا يُنزَّل (القضية #6). ومسار التنزيل خارج وسطاء اللوحة،
        // فيُشترط التحقق الثنائي هنا أيضاً
        $permission = match ($export->exporter) {
            ServiceRequestExporter::class => 'requests.export',
            ProgramRegistrationExporter::class => 'programs.export',
            SubmissionExporter::class => 'submissions.export',
            default => null,
        };

        return $permission !== null
            && $export->user_id === $user->id
            && $user->can($permission)
            && $user->hasEnabledTwoFactorAuthentication();
    }
}
