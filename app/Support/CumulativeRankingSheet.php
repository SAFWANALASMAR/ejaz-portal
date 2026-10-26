<?php

namespace App\Support;

use App\Enums\ActivityEvent;
use App\Filament\Exports\ServiceRequestExporter;
use App\Models\Competition;
use App\Models\CompetitionSubmission;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * تصدير الترتيب التراكمي لمسابقة الطلاب إلى إكسل بتصفية الجدول (CumulativeRanking): بقاعدة تصدير النتائج نفسها
 * (StudentResultsSheet): لمن يدير النتائج، ورقم الهوية مخفي إلا في التصدير الكامل (NationalIdExport)، والتصدير
 * يُسجَّل بعدده ومن صدّره، والخلية التي تبدأ كالصيغة نصٌّ.
 */
final class CumulativeRankingSheet
{
    /**
     * @param  Builder<CompetitionSubmission>  $query
     *
     * @throws AuthorizationException
     * @throws ValidationException كلمة المرور في التصدير الكامل
     */
    public static function download(User $actor, Competition $competition, Builder $query, bool $full = false, ?string $password = null): StreamedResponse
    {
        if (! CompetitionAccess::managesResults($actor, $competition)) {
            throw new AuthorizationException;
        }

        if ($full) {
            NationalIdExport::authorizeFull($actor, $password);
        }

        activity('competitions')->performedOn($competition)->causedBy($actor)
            ->event(ActivityEvent::ResultsExported->value)
            ->withProperties(['cumulative' => true, 'full_identity' => $full, 'rows' => (clone $query)->count(), 'exported_by' => $actor->name])
            ->log(ActivityEvent::ResultsExported->value);

        return response()->streamDownload(function () use ($query, $full): void {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues([
                __('competitions.student.admin.cumulative_rank'),
                __('competitions.entry.reference'),
                __('competitions.student.attributes.full_name'),
                __('competitions.student.attributes.national_id'),
                __('competitions.student.attributes.gender'),
                __('competitions.student.attributes.category_id'),
                __('competitions.student.attributes.grade'),
                __('competitions.student.attributes.region_id'),
                __('competitions.student.admin.rounds_done'),
                __('competitions.student.admin.cumulative_total'),
                __('competitions.student.admin.max_score'),
            ]));

            $query->reorder()
                ->orderByRaw('cumulative_rank ASC NULLS LAST')
                ->orderBy('id')
                ->with(['studentCategory', 'studentRegion'])
                ->chunk(500, function ($submissions) use ($writer, $full): void {
                    foreach ($submissions as $submission) {
                        /** @var CompetitionSubmission $submission */
                        $writer->addRow(Row::fromValues(array_map(fn (string|int|float|null $value): string|int|float|null => is_string($value) ? ServiceRequestExporter::safeCell($value) : $value, [
                            $submission->getAttribute('cumulative_rank') !== null ? (int) $submission->getAttribute('cumulative_rank') : '',
                            (string) $submission->reference,
                            (string) $submission->full_name,
                            (string) ($full ? $submission->national_id : CompetitionSubmission::mask($submission->national_id)),
                            (string) $submission->gender?->getLabel(),
                            (string) $submission->studentCategory?->name,
                            (string) $submission->grade,
                            (string) $submission->studentRegion?->name,
                            (int) $submission->getAttribute('rounds_done'),
                            (float) $submission->getAttribute('cumulative_total'),
                            (float) $submission->getAttribute('cumulative_max'),
                        ])));
                    }
                });

            $writer->close();
        }, 'cumulative-'.$competition->slug.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
