<?php

namespace App\Actions\Competitions;

use App\Enums\QuestionType;
use App\Models\CompetitionCategory;
use App\Models\CompetitionRound;
use App\Models\User;
use App\Support\StudentQuestionsSheet;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OpenSpout\Reader\XLSX\Reader;
use Throwable;

/**
 * استيراد أسئلة مرحلة من ملف إكسل بأعمدة القالب (StudentQuestionsSheet): كل صف سؤال يُحفظ عبر SaveStudentQuestion
 * بفحوصه كلها، والملف كله أو لا شيء: أول صف مخالف يرفض الاستيراد برقمه وسببه.
 *
 * يعيد عدد الأسئلة المستوردة.
 */
class ImportStudentQuestions
{
    private const MAX_ROWS = 1000;

    public function __construct(private SaveStudentQuestion $save) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, CompetitionRound $round, string $path): int
    {
        if (! $actor->can('update', $round->competition)) {
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

        try {
            $reader = new Reader;
            $reader->open($path);

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $index => $row) {
                    // سقف للصفوف المقروءة كلها لا الممتلئة وحدها: ملايين الصفوف الفارغة المضغوطة تستنزف المعالج
                    if ($index > self::MAX_ROWS * 2) {
                        throw ValidationException::withMessages(['file' => __('competitions.student.admin.import_too_many', ['max' => self::MAX_ROWS])]);
                    }

                    if ($index === 1) {
                        continue;
                    }

                    $cells = array_map(fn (mixed $value): string => trim(is_scalar($value) ? (string) $value : ''), $row->toArray());

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
