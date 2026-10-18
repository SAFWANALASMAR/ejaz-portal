<?php

namespace App\Support;

use App\Enums\FormEntryStatus;
use App\Enums\MeetingStatus;
use App\Enums\RegistrationStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\SubmissionStatus;
use App\Models\ArchiveItem;
use App\Models\AssemblyMembership;
use App\Models\Competition;
use App\Models\CompetitionSubmission;
use App\Models\DailyVisit;
use App\Models\FormEntry;
use App\Models\Meeting;
use App\Models\Program;
use App\Models\ProgramRegistration;
use App\Models\ServiceRequest;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * إحصائيات البوابة من بياناتها الفعلية: تُحسب في الخلفية (ejaz:refresh-stats كل عشر دقائق) وتُخزَّن،
 * فلا تُحسب عند كل زيارة (performance.md). ولوحة الإدارة والرئيسية تقرآن المخزّن.
 * والمدفوعات لا تدخل هنا: لها شاشتها للمجلس (docs/plans/admin-stats.md).
 */
final class PortalStats
{
    private const KEY = 'portal.stats';

    /**
     * ما يجوز عرضه في الرئيسية مصدراً آلياً لخانة إحصائية (SiteSettings). وما سواه للإدارة وحدها.
     * والحسابات الموثّقة وحدها، لا كل التسجيلات (منها الآلية والمهجورة). والتسجيلات المؤكدة هي «المستفيدون»
     * في المتطلبات (requirements/07).
     */
    public const PUBLIC = ['verified_users', 'competitions', 'submissions', 'programs', 'registrations'];

    /**
     * @return array<string, int>
     */
    public static function compute(): array
    {
        // الطلبات المرسلة بحالاتها، بلا المسودات التي لا يراها غير أصحابها
        $requests = ServiceRequest::query()->submitted()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        // التسجيلات في برامج غير محذوفة، كإحصائيات المشترك (BuildParticipationStats)
        $registrations = fn (): Builder => ProgramRegistration::query()->whereHas('program', fn (Builder $program) => $program->whereNull('deleted_at'));
        $registrationStatuses = $registrations()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        // أرقام اليوم كما نقلها Visits::persist() قبل الحساب (RefreshPortalStats)
        $visits = DailyVisit::query()->where('date', Visits::today())->first();

        return [
            'users' => User::query()->whereNull('anonymized_at')->count(),
            'verified_users' => User::query()->whereNull('anonymized_at')->whereNotNull('phone_verified_at')->count(),
            'new_users' => User::query()->whereNull('anonymized_at')->where('created_at', '>=', now()->subDays(30))->count(),
            'views_today' => $visits->views ?? 0,
            'visitors_today' => $visits->visitors ?? 0,

            'requests' => (int) $requests->sum(),
            ...collect(ServiceRequestStatus::cases())
                ->reject(fn (ServiceRequestStatus $status): bool => $status === ServiceRequestStatus::Draft)
                ->mapWithKeys(fn (ServiceRequestStatus $status): array => ['requests_'.$status->value => (int) ($requests[$status->value] ?? 0)])
                ->all(),

            'programs' => Program::query()->published()->count(),
            'registrations' => (int) ($registrationStatuses[RegistrationStatus::Confirmed->value] ?? 0),
            'waitlisted' => (int) ($registrationStatuses[RegistrationStatus::Waitlisted->value] ?? 0),
            'attended' => $registrations()->where('status', RegistrationStatus::Confirmed->value)->whereNotNull('attended_at')->count(),
            'competitions' => Competition::query()->published()->count(),
            'submissions' => CompetitionSubmission::query()->where('status', SubmissionStatus::Submitted)->count(),
            'drafts' => CompetitionSubmission::query()->where('status', SubmissionStatus::Draft)->count(),
            'surveys' => Survey::query()->where('is_published', true)->count(),
            'responses' => SurveyResponse::query()->count(),

            // اجتماعات الجمعية العمومية وحدها، لا المجلس ولجانه
            'members' => AssemblyMembership::activeUsers()->count(),
            'upcoming_meetings' => Meeting::query()->generalAssembly()->where('status', MeetingStatus::Scheduled->value)->where('starts_at', '>', now())->count(),
            'held_meetings' => Meeting::query()->generalAssembly()->where('status', MeetingStatus::Held->value)->count(),
            'form_drafts' => FormEntry::query()->where('status', FormEntryStatus::Draft->value)->count(),
            'form_approved' => FormEntry::query()->where('status', FormEntryStatus::Approved->value)->count(),
            'archive_items' => ArchiveItem::query()->count(),
        ];
    }

    /**
     * يحسب ويخزّن، ويعيد السابق للمقارنة.
     *
     * @return array{previous: array<string, int>|null, current: array<string, int>}
     */
    public static function refresh(): array
    {
        $previous = self::cached();
        $current = self::compute();

        // بلا مدة: يبقى آخر حساب حتى يحل محله التالي (سياسة إخلاء Redis في performance.md)
        Cache::forever(self::KEY, ['values' => $current, 'at' => now()->toIso8601String()]);

        return ['previous' => $previous, 'current' => $current];
    }

    /**
     * @return array<string, int>|null
     */
    public static function cached(): ?array
    {
        $stored = Cache::get(self::KEY);

        return is_array($stored) && is_array($stored['values'] ?? null) ? $stored['values'] : null;
    }

    public static function computedAt(): ?string
    {
        $stored = Cache::get(self::KEY);

        return is_array($stored) && is_string($stored['at'] ?? null) ? $stored['at'] : null;
    }
}
