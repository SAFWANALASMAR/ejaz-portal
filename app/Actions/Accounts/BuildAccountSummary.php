<?php

namespace App\Actions\Accounts;

use App\Enums\RegistrationStatus;
use App\Enums\ServiceRequestStatus;
use App\Models\Program;
use App\Models\ProgramRegistration;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * ملخص رئيسية لوحة المستخدم: ما يحتاج تصرفه، وآخر طلباته، وأقرب برامجه. لصاحب الحساب وحده،
 * وبعدد ثابت من الاستعلامات مهما كثرت سجلاته.
 */
class BuildAccountSummary
{
    private const LATEST = 3;

    private const ALERTS = 10;

    /**
     * @return array{
     *     needs_completion: Collection<int, ServiceRequest>,
     *     open_drafts: Collection<int, ServiceRequest>,
     *     latest_requests: Collection<int, ServiceRequest>,
     *     open_requests: int,
     *     upcoming: Collection<int, ProgramRegistration>,
     *     upcoming_count: int,
     * }
     */
    public function handle(User $user): array
    {
        $requests = fn (): Builder => ServiceRequest::query()->whereBelongsTo($user);

        $upcoming = fn (): Builder => ProgramRegistration::query()
            ->whereBelongsTo($user)
            ->where('status', '!=', RegistrationStatus::Cancelled->value)
            ->whereHas('program', fn (Builder $program) => $program->whereNull('deleted_at')->where('starts_at', '>', now()));

        return [
            // طلب واحد لكل فرصة (فهرس فريد)، والحد صريح فلا يكبر العمل بكثرة السجلات
            'needs_completion' => $requests()->where('status', ServiceRequestStatus::NeedsCompletion->value)->with('opening')->latest('id')->limit(self::ALERTS)->get(),
            // المسودة تُذكَّر بها ما دامت فرصتها تقبل التقديم
            'open_drafts' => $requests()->where('status', ServiceRequestStatus::Draft->value)
                ->whereHas('opening', fn (Builder $opening) => $opening->whereNull('deleted_at')->where('is_published', true)
                    ->where(fn (Builder $open) => $open->whereNull('closes_at')->orWhere('closes_at', '>', now())))
                ->with('opening')->latest('id')->limit(self::ALERTS)->get(),
            'latest_requests' => $requests()->with('opening')->latest('id')->limit(self::LATEST)->get(),
            'open_requests' => $requests()->whereIn('status', [
                ServiceRequestStatus::Received->value, ServiceRequestStatus::UnderReview->value, ServiceRequestStatus::NeedsCompletion->value,
            ])->count(),
            'upcoming' => $upcoming()->with('program')
                ->orderBy(Program::query()->select('starts_at')->whereColumn('programs.id', 'program_registrations.program_id'))
                ->limit(self::LATEST)->get(),
            'upcoming_count' => $upcoming()->count(),
        ];
    }
}
