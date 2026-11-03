<?php

namespace App\Support\ProgramStats;

use App\Contracts\ProgramStatsSource;
use App\Enums\LibraryProgramKind;
use App\Support\ProgramTypeStats;

/**
 * برامج المكتبة الثلاثة (قراءة كتاب، والاستماع إلى مادة، ومشاهدة مقطع — docs/decisions/2026-11-02-reading-program.md) من
 * مجاميع ProgramTypeStats::compute() في فرعها: المسجلون المؤكدون، والمكملون بالحضور أو الإتمام (completed_at)، والصفحات المقروءة
 * لنوع القراءة وحده، ودقائق الاستماع والمشاهدة لنوعيهما وحدهما. فتحل صفوفها محل صفوف أنواعها العامة من ProgramTypesSource
 * بمعرّفاتها نفسها، وتظهر خانة الصفحات أو الدقائق لها وحدها. وصف المسابقات من CompetitionsSource وحده.
 */
final class LibraryProgramsSource implements ProgramStatsSource
{
    public function rows(): array
    {
        return collect(ProgramTypeStats::compute())
            ->filter(fn (array $row): bool => LibraryProgramKind::tryFrom($row['key']) !== null)
            ->map(fn (array $row): array => [
                'key' => $row['key'],
                'label' => $row['label'],
                'registered' => $row['registered'],
                'completed' => $row['completed'],
                'pages' => $row['key'] === LibraryProgramKind::Reading->value ? $row['pages'] : null,
                'minutes' => $row['key'] === LibraryProgramKind::Reading->value ? null : $row['minutes'],
            ])
            ->values()
            ->all();
    }
}
