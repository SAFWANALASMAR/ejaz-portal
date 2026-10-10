<?php

namespace App\Actions\Accounts;

use App\Enums\RegistrationStatus;
use App\Enums\SubmissionStatus;
use App\Models\CompetitionSubmission;
use App\Models\ProgramRegistration;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * إحصائيات المشترك في لوحته (requirements/12 §12 د: «عرض الاحصائيات للبرامج المسجل فيها، أو المنجزة»)
 * من البيانات القائمة، لصاحب الحساب وحده، وبثلاثة استعلامات مهما كثرت سجلاته:
 * - المسجل فيها: تسجيلاته المؤكدة (لا قائمة الانتظار ولا الملغاة) في برامج غير محذوفة.
 * - المنجزة: ما سُجّل حضوره منها.
 * - مشاركات المسابقات المرسلة.
 */
class BuildParticipationStats
{
    /**
     * @return array{registered: int, completed: int, entries: int}
     */
    public function handle(User $user): array
    {
        $registrations = fn (): Builder => ProgramRegistration::query()
            ->whereBelongsTo($user)
            ->where('status', RegistrationStatus::Confirmed->value)
            ->whereHas('program', fn (Builder $program) => $program->whereNull('deleted_at'));

        return [
            'registered' => $registrations()->count(),
            'completed' => $registrations()->whereNotNull('attended_at')->count(),
            'entries' => CompetitionSubmission::query()->whereBelongsTo($user)->where('status', SubmissionStatus::Submitted->value)->count(),
        ];
    }
}
