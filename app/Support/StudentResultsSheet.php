<?php

namespace App\Support;

use App\Enums\ActivityEvent;
use App\Filament\Exports\ServiceRequestExporter;
use App\Models\Competition;
use App\Models\CompetitionAttempt;
use App\Models\CompetitionSubmission;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * تصدير نتائج مسابقة الطلاب إلى إكسل بتصفية الجدول، مرتبة بالدرجة ومرقّمة داخل كل مرحلة وفئة: رقم الهوية وجوال ولي الأمر مخفيان إلا آخر أربعة
 * أرقام لكل من يصدّر، وكاملان في التصدير الكامل وحده (NationalIdExport: صلاحية مستقلة وكلمة المرور). والتصدير عملية
 * تُسجَّل بما صُدّر وعدده ومن صدّره. ويُكتب صفاً صفاً بدفعات، فلا تمتلئ الذاكرة بعشرات الآلاف.
 */
final class StudentResultsSheet
{
    /**
     * @param  Builder<CompetitionAttempt>  $query
     *
     * @throws AuthorizationException
     * @throws ValidationException كلمة المرور في التصدير الكامل
     */
    public static function download(User $actor, Competition $competition, Builder $query, bool $full = false, ?string $password = null): StreamedResponse
    {
        // بيانات قاصرين: لمن رُبط به التصدير وحده، ولو وصل الطلب من غير الزر المخفي
        if (! CompetitionAccess::managesResults($actor, $competition)) {
            throw new AuthorizationException;
        }

        if ($full) {
            NationalIdExport::authorizeFull($actor, $password);
        }

        activity('competitions')->performedOn($competition)->causedBy($actor)
            ->event(ActivityEvent::ResultsExported->value)
            ->withProperties(['full_identity' => $full, 'rows' => (clone $query)->count(), 'exported_by' => $actor->name])
            ->log(ActivityEvent::ResultsExported->value);

        $fields = $competition->fields()->get();

        return response()->streamDownload(function () use ($query, $full, $fields): void {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues([
                __('competitions.student.admin.rank'),
                __('competitions.entry.reference'),
                __('competitions.student.attributes.full_name'),
                __('competitions.student.attributes.national_id'),
                __('competitions.student.attributes.gender'),
                __('competitions.student.attributes.category_id'),
                __('competitions.student.attributes.grade'),
                __('competitions.student.attributes.region_id'),
                __('competitions.student.attributes.guardian_phone'),
                ...$fields->pluck('label')->all(),
                __('competitions.student.question_attributes.round_id'),
                __('competitions.student.attempt_reference'),
                __('competitions.student.admin.score'),
                __('competitions.student.admin.max_score'),
                __('competitions.entry.submitted_at'),
                __('competitions.student.admin.auto_submitted'),
                __('competitions.student.admin.qualified'),
            ]));

            // الترتيب داخل كل مرحلة وفئة (مراجعة المشرف): بنك كل فئة غير بنك غيرها، والسحب للمتساوين بهما كذلك
            $ranks = [];

            // مرتبة بالدرجة أياً كان ترتيب الجدول، فالترتيب في العمود الأول درجةٌ لا موضع في الجدول
            $query->reorder()
                ->orderByRaw('COALESCE(competition_attempts.score, 0) + COALESCE(competition_attempts.essay_score, 0) DESC')
                ->orderBy('competition_attempts.id')
                ->with(['round', 'submission.studentCategory', 'submission.studentRegion'])->chunk(500, function ($attempts) use ($writer, $full, $fields, &$ranks): void {
                    foreach ($attempts as $attempt) {
                        /** @var CompetitionAttempt $attempt */
                        $submission = $attempt->submission;
                        $group = $attempt->round_id.':'.$submission->category_id;
                        $total = (float) $attempt->score + (float) $attempt->essay_score;
                        // المتساوون في الدرجة يأخذون الترتيب نفسه، والتالي بعدهم بموضعه (1، 1، 3)
                        $seen = ($ranks[$group]['seen'] ?? 0) + 1;
                        $rank = isset($ranks[$group]) && $ranks[$group]['total'] === $total ? $ranks[$group]['rank'] : $seen;
                        $ranks[$group] = ['seen' => $seen, 'rank' => $rank, 'total' => $total];

                        $writer->addRow(self::row([
                            $rank,
                            (string) $submission->reference,
                            (string) $submission->full_name,
                            (string) ($full ? $submission->national_id : CompetitionSubmission::mask($submission->national_id)),
                            (string) $submission->gender?->getLabel(),
                            (string) $submission->studentCategory?->name,
                            (string) $submission->grade,
                            (string) $submission->studentRegion?->name,
                            // بصيغة محلية كبقية التصديرات، فلا يبدأ بـ + فيُقتبس ('+966…)
                            $full ? SaudiPhone::display($submission->guardian_phone) : (string) CompetitionSubmission::mask($submission->guardian_phone),
                            ...$fields->map(fn ($field): string => (string) (((array) $submission->extra)[(string) $field->id] ?? ''))->all(),
                            $attempt->round->name,
                            (string) $attempt->reference,
                            (float) $attempt->score + (float) $attempt->essay_score,
                            (float) $attempt->max_score,
                            Dates::display($attempt->submitted_at, withTime: true),
                            $attempt->auto_submitted ? __('competitions.student.admin.yes') : '',
                            $attempt->qualified_at !== null ? __('competitions.student.admin.yes') : '',
                        ]));
                    }
                });

            $writer->close();
        }, 'results-'.$competition->slug.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * صف بخلايا نصية آمنة: النص الذي يبدأ بـ = أو + أو - أو @ يُكتب نصاً لا صيغة (حقل مخصص يكتبه الطالب).
     *
     * @param  list<string|int|float|null>  $values
     */
    private static function row(array $values): Row
    {
        return Row::fromValues(array_map(fn (string|int|float|null $value): string|int|float|null => is_string($value) ? ServiceRequestExporter::safeCell($value) : $value, $values));
    }

    public static function score(CompetitionAttempt $attempt): string
    {
        return Number::format((float) $attempt->score + (float) $attempt->essay_score, maxPrecision: 2).' / '.Number::format((float) $attempt->max_score, maxPrecision: 2);
    }
}
