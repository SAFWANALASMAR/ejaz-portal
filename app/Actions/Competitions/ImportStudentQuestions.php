<?php

namespace App\Actions\Competitions;

use App\Enums\QuestionType;
use App\Models\CompetitionCategory;
use App\Models\CompetitionRound;
use App\Models\User;
use App\Support\CompetitionAccess;
use App\Support\StudentQuestionsSheet;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OpenSpout\Reader\XLSX\Reader;
use Throwable;
use ZipArchive;

/**
 * استيراد أسئلة مرحلة من ملف إكسل بأعمدة القالب (StudentQuestionsSheet): كل صف سؤال يُحفظ عبر SaveStudentQuestion
 * بفحوصه كلها، والملف كله أو لا شيء: أول صف مخالف يرفض الاستيراد برقمه وسببه.
 *
 * يعيد عدد الأسئلة المستوردة.
 */
class ImportStudentQuestions
{
    private const MAX_ROWS = 1000;

    // حجم الملف بعد فك الضغط: بنك من ألف سؤال بنصوصه أقل من هذا بكثير
    private const MAX_UNCOMPRESSED_BYTES = 20 * 1024 * 1024;

    public function __construct(private SaveStudentQuestion $save) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, CompetitionRound $round, string $path): int
    {
        if (! CompetitionAccess::allows($actor, 'competitions.update')) {
            throw new AuthorizationException;
        }

        $rows = $this->read($path);
        $types = collect(QuestionType::student())->mapWithKeys(fn (QuestionType $type): array => [$type->getLabel() => $type]);
        $categories = CompetitionCategory::query()->pluck('id', 'name');
        $trueFalse = [(string) __('competitions.student.true_false.t') => 't', (string) __('competitions.student.true_false.f') => 'f'];

        return DB::transaction(function () use ($actor, $round, $rows, $types, $categories, $trueFalse): int {
            foreach ($rows as $number => $cells) {
                [$typeLabel, $categoryName, $passage, $prompt] = array_slice($cells, 0, 4);
                $options = array_slice($cells, 4, StudentQuestionsSheet::OPTIONS);
                $correct = $cells[4 + StudentQuestionsSheet::OPTIONS] ?? '';
                $points = $cells[5 + StudentQuestionsSheet::OPTIONS] ?? '';

                $type = $types->get($typeLabel);

                if ($type === null) {
                    throw $this->rowError($number, __('competitions.student.admin.import_type'));
                }

                if ($categoryName !== '' && ! $categories->has($categoryName)) {
                    throw $this->rowError($number, __('competitions.student.admin.import_category'));
                }

                $data = [
                    'type' => $type->value,
                    'prompt' => $prompt,
                    'category_id' => $categoryName === '' ? null : $categories->get($categoryName),
                    'passage_id' => $passage === '' ? null : (int) ltrim($passage, '#'),
                    'points' => $points === '' ? 1 : (int) $points,
                ];

                if ($type === QuestionType::TrueFalse) {
                    $data['true_false'] = $trueFalse[$correct] ?? null;
                } elseif ($type !== QuestionType::Essay) {
                    $labels = array_values(array_filter($options, fn (string $label): bool => $label !== ''));
                    $data['options'] = array_map(fn (string $label, int $index): array => ['label' => $label, 'is_correct' => (string) ($index + 1) === $correct], $labels, array_keys($labels));
                }

                try {
                    $this->save->handle($actor, $round, null, $data);
                } catch (ValidationException $exception) {
                    throw $this->rowError($number, (string) collect($exception->errors())->flatten()->first());
                }
            }

            return count($rows);
        });
    }

    /**
     * مجموع أحجام ملفات الأرشيف بعد فك الضغط (xlsx أرشيف zip)، أو ما فوق الحد إن لم يُقرأ أرشيفاً.
     */
    private static function uncompressedSize(string $path): int
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            return PHP_INT_MAX;
        }

        $total = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $total += (int) ($zip->statIndex($i)['size'] ?? 0);
        }

        $zip->close();

        return $total;
    }

    private function rowError(int $row, string $reason): ValidationException
    {
        return ValidationException::withMessages(['file' => __('competitions.student.admin.import_row', ['row' => $row, 'reason' => $reason])]);
    }

    /**
     * صفوف البيانات بعد صف العناوين، برقم الصف في الملف، والخلايا نصوصاً مشذبة. والصف الفارغ يُتخطى.
     *
     * @return array<int, list<string>>
     *
     * @throws ValidationException
     */
    private function read(string $path): array
    {
        $rows = [];

        // حجم الملف بعد فك ضغطه قبل فتحه: ملايين الصفوف الفارغة المضغوطة في ملف صغير تستنزف المعالج (مراجعة المشرف)
        if (self::uncompressedSize($path) > self::MAX_UNCOMPRESSED_BYTES) {
            throw ValidationException::withMessages(['file' => __('competitions.student.admin.import_too_many', ['max' => self::MAX_ROWS])]);
        }

        try {
            $reader = new Reader;
            $reader->open($path);

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $index => $row) {
                    if ($index === 1) {
                        continue;
                    }

                    // والخلية التي حماها التصدير من الصيغ بفاصلة عليا (ServiceRequestExporter::safeCell) تعود كما كانت، ولو
                    // سبقت علامتَها فراغاتٌ أو NBSP أو BOM كما يقتبسها safeCell (الاختبار الأمني 0.14.0)
                    $cells = array_map(fn (mixed $value): string => (string) preg_replace("/^'(?=[\\s\\x{00A0}\\x{FEFF}]*[=+\\-@\\t\\r])/u", '', trim(is_scalar($value) ? (string) $value : '')), $row->toArray());

                    if (implode('', $cells) === '') {
                        continue;
                    }

                    $rows[$index] = array_pad($cells, 6 + StudentQuestionsSheet::OPTIONS, '');

                    if (count($rows) > self::MAX_ROWS) {
                        throw ValidationException::withMessages(['file' => __('competitions.student.admin.import_too_many', ['max' => self::MAX_ROWS])]);
                    }
                }

                break;
            }

            $reader->close();
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw ValidationException::withMessages(['file' => __('competitions.student.admin.import_unreadable')]);
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => __('competitions.student.admin.import_empty')]);
        }

        return $rows;
    }
}
