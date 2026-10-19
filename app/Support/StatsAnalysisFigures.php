<?php

namespace App\Support;

use App\Enums\RegistrationStatus;
use App\Enums\SaudiRegion;
use App\Enums\SubmissionStatus;
use App\Models\Competition;
use App\Models\CompetitionSubmission;
use App\Models\Program;
use App\Models\ProgramRegistration;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * أرقام تحليل الإحصائيات لفترة، يبنيها الخادم وحده: البرامج والتسجيل والحضور وقائمة الانتظار، والمسابقات ومشاركاتها.
 * أعداد مجمّعة فقط، ومعها أسماء المحتوى المنشور (البرامج والمسابقات وأنواعها) والمناطق، ولا اسم ولا بريد ولا جوال
 * ولا معرّف لأحد. وهي ما يُرسل إلى المزود، وما يُعرض بجانب التقرير.
 *
 * الشكل: المجموعة ← المفتاح ← [التسمية، العدد].
 */
final class StatsAnalysisFigures
{
    private const TOP = 5;

    /**
     * @return array<string, array<string, array{label: string, value: int}>>
     */
    public static function compute(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $tz = (string) config('app.display_timezone');
        $start = $from->shiftTimezone($tz)->startOfDay()->utc();
        $end = $to->shiftTimezone($tz)->endOfDay()->utc();
        $within = fn (string $column): \Closure => fn (Builder $query) => $query->whereBetween($column, [$start, $end]);

        // تسجيلات في برامج غير محذوفة، كإحصائيات اللوحة (PortalStats)
        $registrations = fn (): Builder => ProgramRegistration::query()->whereHas('program', fn (Builder $program) => $program->whereNull('deleted_at'));
        $statuses = $registrations()->where($within('created_at'))->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $byType = $registrations()->where($within('program_registrations.created_at'))
            ->join('programs', 'programs.id', '=', 'program_registrations.program_id')
            ->leftJoin('program_types', 'program_types.id', '=', 'programs.program_type_id')
            ->toBase()
            ->selectRaw("coalesce(program_types.name, '') as name, count(*) as total")
            ->groupBy('program_types.name')
            ->orderByDesc('total')
            ->pluck('total', 'name');

        $topPrograms = $registrations()->where($within('program_registrations.created_at'))
            ->where('program_registrations.status', RegistrationStatus::Confirmed->value)
            ->join('programs', 'programs.id', '=', 'program_registrations.program_id')
            ->toBase()
            ->selectRaw('programs.title as title, count(*) as total')
            ->groupBy('programs.id', 'programs.title')
            ->orderByDesc('total')
            ->limit(self::TOP)
            ->pluck('total', 'title');

        $submissions = fn (): Builder => CompetitionSubmission::query()->whereHas('competition', fn (Builder $competition) => $competition->whereNull('deleted_at'));

        $byCompetition = $submissions()->where('competition_submissions.status', SubmissionStatus::Submitted->value)
            ->where($within('competition_submissions.submitted_at'))
            ->join('competitions', 'competitions.id', '=', 'competition_submissions.competition_id')
            ->toBase()
            ->selectRaw('competitions.title as title, count(*) as total')
            ->groupBy('competitions.id', 'competitions.title')
            ->orderByDesc('total')
            ->limit(self::TOP)
            ->pluck('total', 'title');

        $byRegion = $submissions()->where('status', SubmissionStatus::Submitted->value)->where($within('submitted_at'))->whereNotNull('region')
            ->toBase()->selectRaw('region, count(*) as total')->groupBy('region')->orderByDesc('total')->pluck('total', 'region');

        return [
            'programs' => [
                'programs_published' => self::row('programs_published', Program::query()->published()->count()),
                'programs_started' => self::row('programs_started', Program::query()->published()->where($within('starts_at'))->count()),
                'registrations' => self::row('registrations', (int) $statuses->sum()),
                'confirmed' => self::row('confirmed', (int) ($statuses[RegistrationStatus::Confirmed->value] ?? 0)),
                'waitlisted' => self::row('waitlisted', (int) ($statuses[RegistrationStatus::Waitlisted->value] ?? 0)),
                'cancelled' => self::row('cancelled', (int) ($statuses[RegistrationStatus::Cancelled->value] ?? 0)),
                'attended' => self::row('attended', $registrations()->where('status', RegistrationStatus::Confirmed->value)->where($within('attended_at'))->count()),
            ],
            'program_types' => self::named($byType, 'program_type', fn (string $name): string => $name !== '' ? $name : __('analysis.figures.untyped')),
            'top_programs' => self::named($topPrograms, 'program', fn (string $title): string => $title),
            'competitions' => [
                'competitions_published' => self::row('competitions_published', Competition::query()->published()->count()),
                'submitted' => self::row('submitted', $submissions()->where('status', SubmissionStatus::Submitted->value)->where($within('submitted_at'))->count()),
                'drafts' => self::row('drafts', $submissions()->where('status', SubmissionStatus::Draft->value)->where($within('created_at'))->count()),
            ],
            'top_competitions' => self::named($byCompetition, 'competition', fn (string $title): string => $title),
            'regions' => self::named($byRegion, 'region', fn (string $region): string => SaudiRegion::tryFrom($region)?->getLabel() ?? $region),
        ];
    }

    /**
     * @return array{label: string, value: int}
     */
    private static function row(string $key, int $value): array
    {
        return ['label' => (string) __('analysis.figures.'.$key), 'value' => $value];
    }

    /**
     * @param  iterable<string, int|string>  $totals
     * @param  \Closure(string): string  $label
     * @return array<string, array{label: string, value: int}>
     */
    private static function named(iterable $totals, string $prefix, \Closure $label): array
    {
        $rows = [];
        $index = 0;

        foreach ($totals as $name => $total) {
            $rows[$prefix.'_'.(++$index)] = ['label' => $label((string) $name), 'value' => (int) $total];
        }

        return $rows;
    }
}
