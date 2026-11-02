<?php

namespace App\Actions\Accounts;

use App\Enums\RegistrationStatus;
use App\Models\CompetitionSubmission;
use App\Models\ProgramRegistration;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * إحصائيات المشترك في لوحته (requirements/12 §12 د: «عرض الاحصائيات للبرامج المسجل فيها، أو المنجزة»)
 * من البيانات القائمة، لصاحب الحساب وحده، وباستعلامين مهما كثرت سجلاته (المسجل فيها والمنجزة معاً):
 * - المسجل فيها: تسجيلاته المؤكدة (لا قائمة الانتظار ولا الملغاة) في برامج غير محذوفة.
 * - المنجزة: ما سُجّل حضوره منها.
 * - مشاركاته في المسابقات كما تظهر في «مشاركاتي».
 */
class BuildParticipationStats
{
    /**
     * @return array{registered: int, completed: int, entries: int}
     */
    public function handle(User $user): array
    {
        $registrations = ProgramRegistration::query()
            ->whereBelongsTo($user)
            ->where('status', RegistrationStatus::Confirmed->value)
            ->whereHas('program', fn (Builder $program) => $program->whereNull('deleted_at'))
            ->toBase()
            ->selectRaw('count(*) as registered, count(attended_at) as completed')
            ->first();

        return [
            'registered' => (int) ($registrations->registered ?? 0),
            'completed' => (int) ($registrations->completed ?? 0),
            // من مصدر «مشاركاتي» نفسه (CompetitionSubmission::ownedBy — ملاحظات المدير، 1 نوفمبر 2026، البند ١٧)
            'entries' => CompetitionSubmission::query()->ownedBy($user)->count(),
        ];
    }
}
