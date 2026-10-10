<?php

namespace App\Actions\Competitions;

use App\Enums\QuestionMedia;
use App\Enums\QuestionType;
use App\Enums\RoleName;
use App\Enums\WorkType;
use App\Models\Competition;
use App\Models\CompetitionCriterion;
use App\Models\CompetitionQuestion;
use App\Models\User;
use App\Support\CompetitionMedia;
use App\Support\ExamQuestions;
use App\Support\ImageSanitizer;
use App\Support\VideoProbe;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidatorInstance;

/**
 * حفظ مسابقة بمعاييرها وأسئلتها: الصلاحية، ثم فحص المدخلات كلها، ثم الحفظ في معاملة واحدة.
 * يُفحص هنا لا في النموذج وحده، فيسري من أي مكان استُدعي منه.
 *
 * المدخل بشكل نموذج اللوحة: criteria [{id?, name, weight, max_score}]، وquestions
 * [{id?, type, prompt, options [{label, is_correct}], points, is_required, max_video_seconds,
 * max_video_mb}]. والمعرّف لا يُعتمد إلا لمعيار أو سؤال من المسابقة نفسها.
 */
class SaveCompetition
{
    /**
     * مفاتيح خيارات الاختيار من متعدد بترتيبها.
     */
    private const OPTION_KEYS = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j'];

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, Competition $competition, array $data): Competition
    {
        $allowed = $competition->exists ? $actor->can('update', $competition) : $actor->can('create', Competition::class);

        // المسابقة المحذوفة من اللوحة لا تُعدَّل حتى تُستعاد، ويُعاد الفحص على الصف المقفول
        if (! $allowed || ($competition->exists && $competition->trashed())) {
            throw new AuthorizationException;
        }

        // فحص أول قبل التحقق من المدخلات، ويُعاد بعد قفل صف المسابقة في المعاملة
        $requested = $data;
        $this->checkLocks($competition, $requested);

        $data = $this->validate($competition, $data);

        // ما لم يصل من المعايير أو الأسئلة يبقى كما هو
        $criteria = $data['criteria'] ?? null;
        $questions = $data['questions'] ?? null;
        $reviewers = $data['reviewers'] ?? null;
        unset($data['criteria'], $data['questions'], $data['reviewers']);

        if ($reviewers !== null) {
            $this->checkReviewers($competition, array_map('intval', $reviewers));
        }

        $data['categories'] = array_values(array_unique(array_filter(array_map('trim', $data['categories'] ?? []), fn (string $category): bool => $category !== '')));

        $media = $this->prepareMedia($competition, ['questions' => $questions ?? [], ...$data]);
        $data = array_diff_key($media, ['questions' => true]);
        $mediaBefore = $this->mediaPaths($competition);

        // الاختبار الإلكتروني بلا أعمال: حقلها مخفي في اللوحة فلا يصل، فتُفرَّغ هنا
        if ((bool) ($data['exam_mode'] ?? $competition->exam_mode)) {
            $data['work_types'] = null;
        }

        DB::transaction(function () use ($competition, $data, $requested, $criteria, $questions, $reviewers): void {
            // حفظان متزامنان للمسابقة نفسها: الثاني ينتظر، فلا يحذف ما أضافه الأول. والأقفال تُفحص من
            // جديد على الصف المقفول: أول مشاركة أو إسناد أو سحب وصل بين الفحص الأول والقفل
            if ($competition->exists) {
                $locked = Competition::query()->withTrashed()->whereKey($competition->id)->lockForUpdate()->firstOrFail();

                if ($locked->trashed()) {
                    throw new AuthorizationException;
                }

                $this->checkLocks($locked, $requested);
            }

            $competition->fill($data)->save();
            if ($criteria !== null) {
                $this->syncCriteria($competition, $criteria);
            }

            if ($questions !== null) {
                $this->syncQuestions($competition, $questions);
            }

            if ($reviewers !== null) {
                $competition->reviewers()->sync(array_map('intval', $reviewers));
            }
        });

        // بعد الاعتماد: الوسائط التي خرجت من المسابقة تُحذف، وأسئلة الاختبار في الذاكرة المؤقتة تُمسح
        // وما زال مستعملاً في مسابقة أخرى لا يُحذف
        $removed = array_values(array_filter(
            array_diff($mediaBefore, $this->mediaPaths($competition)),
            fn (string $path): bool => ! $this->usedElsewhere($competition, $path),
        ));

        if ($removed !== []) {
            Storage::disk(CompetitionMedia::disk())->delete($removed);
        }

        ExamQuestions::forget($competition->id);

        return $competition->refresh();
    }

    /**
     * بعد أول مشاركة لا يصل بنك الأسئلة من اللوحة (حقله معطل)، ولا يُقبل من غيرها. والمواعيد بعد
     * السحب أو الاعتماد، والمعايير بعد أول إسناد، كذلك.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function checkLocks(Competition $competition, array $data): void
    {
        if (array_key_exists('questions', $data) && $competition->questionsLocked()) {
            throw ValidationException::withMessages(['questions' => __('competitions.validation.questions_locked')]);
        }

        if ($competition->scheduleLocked() && $this->changesSchedule($competition, $data)) {
            throw ValidationException::withMessages(['closes_at' => __('competitions.validation.schedule_locked')]);
        }

        if (array_key_exists('criteria', $data) && $competition->criteriaLocked()) {
            throw ValidationException::withMessages(['criteria' => __('competitions.validation.criteria_locked')]);
        }

        // نوع المسابقة لا يتغير بعد أول مشاركة، لأن مراحل المشارك وإجاباته مربوطة به
        if (array_key_exists('exam_mode', $data) && $competition->exists && (bool) $data['exam_mode'] !== $competition->exam_mode
            && $competition->submissions()->withTrashed()->exists()) {
            throw ValidationException::withMessages(['exam_mode' => __('competitions.exam_validation.exam_mode_locked')]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validate(Competition $competition, array $data): array
    {
        $validator = Validator::make($data, [
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('competitions', 'slug')->ignore($competition->id)],
            'description' => ['nullable', 'string', 'max:5000'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'prizes' => ['nullable', 'string', 'max:2000'],
            'categories' => ['nullable', 'array', 'max:20'],
            'categories.*' => ['string', 'max:60'],
            'opens_at' => ['required', 'date'],
            'closes_at' => ['required', 'date', 'after:opens_at'],
            'shuffle_questions' => ['boolean'],
            'shuffle_options' => ['boolean'],
            'is_published' => ['boolean'],
            'exam_mode' => ['boolean'],
            'intro_video_path' => ['nullable', 'string', 'max:255'],
            'work_types' => ['nullable', 'array', 'max:'.count(WorkType::cases())],
            'work_types.*' => ['distinct', Rule::enum(WorkType::class)],

            'reviewers' => ['nullable', 'array', 'max:200'],
            'reviewers.*' => ['integer', 'distinct'],

            'criteria' => ['nullable', 'array', 'max:20'],
            'criteria.*.id' => ['nullable', 'integer', 'distinct'],
            'criteria.*.name' => ['required', 'string', 'max:150'],
            'criteria.*.weight' => ['required', 'integer', 'min:1', 'max:100'],
            'criteria.*.max_score' => ['required', 'integer', 'min:1', 'max:1000'],

            'questions' => ['nullable', 'array', 'max:200'],
            'questions.*.id' => ['nullable', 'integer', 'distinct'],
            'questions.*.type' => ['required', Rule::enum(QuestionType::class)],
            'questions.*.prompt' => ['required', 'string', 'max:2000'],
            'questions.*.is_required' => ['boolean'],
            'questions.*.options' => ['nullable', 'array', 'max:'.count(self::OPTION_KEYS)],
            'questions.*.options.*.label' => ['required', 'string', 'max:300'],
            'questions.*.options.*.is_correct' => ['boolean'],
            'questions.*.points' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'questions.*.max_video_seconds' => ['nullable', 'integer', 'min:1', 'max:'.config('competitions.video.max_seconds')],
            'questions.*.max_video_mb' => ['nullable', 'integer', 'min:1', 'max:'.config('competitions.video.max_mb')],
            'questions.*.context_text' => ['nullable', 'string', 'max:3000'],
            'questions.*.media_type' => ['nullable', Rule::enum(QuestionMedia::class)],
            'questions.*.media_path' => ['nullable', 'string', 'max:255'],
        ], attributes: __('competitions.attributes'));

        // الفحوص المركبة بعد سلامة كل حقل، فلا يتكرر الخطأ نفسه
        $validator->after(function (ValidatorInstance $validator) use ($competition, $data): void {
            if ($validator->errors()->isEmpty()) {
                $this->checkCriteriaAndQuestions($validator, $competition, $data);
            }
        });

        return $validator->validate();
    }

    /**
     * أوزان المعايير مجموعها 100، ولكل سؤال اختيار خياران فأكثر وخيار صحيح واحد، ولكل سؤال فيديو
     * حداه، ولا تُنشر مسابقة بلا أسئلة.
     *
     * @param  array<string, mixed>  $data
     */
    private function checkCriteriaAndQuestions(ValidatorInstance $validator, Competition $competition, array $data): void
    {
        $criteria = is_array($data['criteria'] ?? null) ? $data['criteria'] : [];
        $questions = is_array($data['questions'] ?? null) ? $data['questions'] : [];

        if ($criteria !== [] && array_sum(array_map(fn ($criterion): int => (int) ($criterion['weight'] ?? 0), $criteria)) !== 100) {
            $validator->errors()->add('criteria', __('competitions.validation.weights_total'));
        }

        foreach (array_values($questions) as $index => $question) {
            $number = $index + 1;
            $type = self::type($question['type'] ?? null);

            if ($type === QuestionType::MultipleChoice) {
                $options = is_array($question['options'] ?? null) ? $question['options'] : [];
                $correct = count(array_filter($options, fn ($option): bool => (bool) ($option['is_correct'] ?? false)));

                if (count($options) < 2 || $correct !== 1) {
                    $validator->errors()->add('questions', __('competitions.validation.choices', ['number' => $number]));
                }

                // سؤال يُصحَّح آلياً ولا درجة له لا معنى له
                if ((int) ($question['points'] ?? 0) < 1) {
                    $validator->errors()->add('questions', __('competitions.validation.points', ['number' => $number]));
                }
            }

            if ($type === QuestionType::Video && (blank($question['max_video_seconds'] ?? null) || blank($question['max_video_mb'] ?? null))) {
                $validator->errors()->add('questions', __('competitions.validation.video_limits', ['number' => $number]));
            }
        }

        $this->checkExam($validator, $competition, $data, $questions);

        $hasQuestions = array_key_exists('questions', $data) ? $questions !== [] : $competition->exists && $competition->questions()->exists();

        if (($data['is_published'] ?? false) && ! $hasQuestions) {
            $validator->errors()->add('is_published', __('competitions.validation.needs_questions'));
        }
    }

    /**
     * الاختبار الإلكتروني (docs/plans/competition-exam.md): أسئلته اختيار من متعدد بأربعة خيارات بالضبط،
     * وبلا أعمال مرفوعة، ولا يُنشر بلا فيديو تعريفي. ولكل سؤال وسيطه ونوعه معاً، والمسارات من مجلد الوسائط.
     *
     * @param  array<string, mixed>  $data
     * @param  array<mixed>  $questions
     */
    private function checkExam(ValidatorInstance $validator, Competition $competition, array $data, array $questions): void
    {
        $exam = (bool) ($data['exam_mode'] ?? $competition->exam_mode);
        $intro = array_key_exists('intro_video_path', $data) ? $data['intro_video_path'] : $competition->intro_video_path;
        $published = (bool) ($data['is_published'] ?? $competition->is_published);

        // في مجلد الوسائط، ولا يشير إلى ملف مسابقة أخرى (فيُعاد ترميزه أو يُحذف معها)
        if (filled($intro) && (! CompetitionMedia::inDirectory((string) $intro) || $this->usedElsewhere($competition, (string) $intro))) {
            $validator->errors()->add('intro_video_path', __('competitions.exam_validation.media_path'));
        }

        foreach (array_values($questions) as $index => $question) {
            $number = $index + 1;
            $hasType = filled($question['media_type'] ?? null);
            $path = $question['media_path'] ?? null;

            if ($hasType !== filled($path)) {
                $validator->errors()->add('questions', __('competitions.exam_validation.media_missing', ['number' => $number]));
            } elseif (filled($path) && (! CompetitionMedia::inDirectory((string) $path) || $this->usedElsewhere($competition, (string) $path))) {
                $validator->errors()->add('questions', __('competitions.exam_validation.media_path'));
            }

            $options = is_array($question['options'] ?? null) ? $question['options'] : [];

            if ($exam && (self::type($question['type'] ?? null) !== QuestionType::MultipleChoice || count($options) !== 4)) {
                $validator->errors()->add('questions', __('competitions.exam_validation.four_options', ['number' => $number]));
            }
        }

        if (! $exam) {
            return;
        }

        // بنك محفوظ من قبل لم يصل في هذا الطلب: يُفحص المحفوظ نفسه
        if (! array_key_exists('questions', $data) && $competition->exists) {
            foreach ($competition->questions as $index => $question) {
                if ($question->type !== QuestionType::MultipleChoice || count($question->optionList()) !== 4) {
                    $validator->errors()->add('questions', __('competitions.exam_validation.four_options', ['number' => $index + 1]));
                }
            }
        }

        if ($published && blank($intro)) {
            $validator->errors()->add('intro_video_path', __('competitions.exam_validation.needs_intro'));
        }
    }

    /**
     * الملفات الجديدة بعد التحقق: الفيديو التعريفي MP4 تُقرأ مدته الحقيقية، ووسيط السؤال نوعه من محتواه
     * مطابق لنوعه المختار، والصورة يُعاد ترميزها. والمخالف يُرفض.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function prepareMedia(Competition $competition, array $data): array
    {
        $disk = CompetitionMedia::disk();

        if (filled($data['intro_video_path'] ?? null) && $data['intro_video_path'] !== $competition->intro_video_path) {
            $seconds = CompetitionMedia::mime((string) $data['intro_video_path']) === 'video/mp4' ? $this->videoSeconds((string) $data['intro_video_path']) : null;

            if ($seconds === null) {
                throw ValidationException::withMessages(['intro_video_path' => __('competitions.exam_validation.intro_unreadable')]);
            }

            $data['intro_video_seconds'] = $seconds;
        } elseif (array_key_exists('intro_video_path', $data) && blank($data['intro_video_path'])) {
            $data['intro_video_seconds'] = null;
        }

        $existing = $competition->exists ? $competition->questions()->pluck('media_path')->filter()->all() : [];

        foreach (array_values((array) ($data['questions'] ?? [])) as $index => $question) {
            $path = $question['media_path'] ?? null;

            if (blank($path) || in_array($path, $existing, true)) {
                continue;
            }

            $raw = $question['media_type'] ?? null;
            $type = $raw instanceof QuestionMedia ? $raw : QuestionMedia::tryFrom(is_string($raw) ? $raw : '');

            if ($type === null || ! in_array(CompetitionMedia::mime((string) $path), $type->mimeTypes(), true)) {
                throw ValidationException::withMessages(['questions' => __('competitions.exam_validation.media_type', ['number' => $index + 1])]);
            }

            if ($type === QuestionMedia::Image) {
                try {
                    ImageSanitizer::strip($disk, (string) $path);
                } catch (\Throwable) {
                    throw ValidationException::withMessages(['questions' => __('competitions.exam_validation.media_type', ['number' => $index + 1])]);
                }
            }
        }

        return $data;
    }

    /**
     * مدة المقطع الحقيقية بالثواني (VideoProbe)، من نسخة محلية مؤقتة فيصلح القرص السحابي أيضاً.
     */
    private function videoSeconds(string $path): ?int
    {
        $source = Storage::disk(CompetitionMedia::disk())->readStream($path);
        $local = tmpfile();

        if ($source === null || $local === false) {
            return null;
        }

        try {
            stream_copy_to_stream($source, $local);
            rewind($local);
            $seconds = VideoProbe::seconds($local);
        } finally {
            fclose($source);
            fclose($local);
        }

        return $seconds === null || $seconds <= 0 ? null : (int) ceil($seconds);
    }

    /**
     * المسار مستعمل في مسابقة أخرى (فيديو تعريفي أو وسيط سؤال)، ولو محذوفة حذفاً ناعماً.
     */
    private function usedElsewhere(Competition $competition, string $path): bool
    {
        return Competition::query()->withTrashed()->whereKeyNot($competition->id ?? 0)->where('intro_video_path', $path)->exists()
            || CompetitionQuestion::query()->where('media_path', $path)->where('competition_id', '!=', $competition->id ?? 0)->exists();
    }

    /**
     * مسارات الوسائط في المسابقة (الفيديو التعريفي ووسائط الأسئلة)، لحذف ما خرج منها بعد الحفظ.
     *
     * @return list<string>
     */
    private function mediaPaths(Competition $competition): array
    {
        if (! $competition->exists) {
            return [];
        }

        return array_values(array_filter([
            $competition->intro_video_path,
            ...$competition->questions()->pluck('media_path')->all(),
        ], fn ($path): bool => is_string($path) && CompetitionMedia::inDirectory($path)));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function changesSchedule(Competition $competition, array $data): bool
    {
        foreach (['opens_at', 'closes_at'] as $field) {
            // بدقة الدقيقة: نموذج اللوحة يحذف الثواني، فالموعد المعروض كما هو لا يُعدّ تغييراً
            if (array_key_exists($field, $data) && ! $competition->{$field}->copy()->startOfMinute()->equalTo(Carbon::parse((string) $data[$field])->startOfMinute())) {
                return true;
            }
        }

        return array_key_exists('is_published', $data) && (bool) $data['is_published'] !== $competition->is_published;
    }

    /**
     * المحكّم المضاف حساب نشط بدور reviewer. ولا يُرفع من المسابقة محكّم أُسندت إليه مشاركات فيها.
     *
     * @param  list<int>  $ids
     *
     * @throws ValidationException
     */
    private function checkReviewers(Competition $competition, array $ids): void
    {
        // الشرط على المضافين فقط، فمحكّم مرتبط عُطّل حسابه لا يمنع حفظ المسابقة
        $current = $competition->exists ? $competition->reviewers()->pluck('users.id')->all() : [];
        $added = array_values(array_diff($ids, $current));
        $valid = User::query()->whereKey($added)->where('is_active', true)->role(RoleName::Reviewer->value)->count();

        if ($valid !== count($added)) {
            throw ValidationException::withMessages(['reviewers' => __('competitions.validation.reviewers')]);
        }

        $assigned = $competition->exists
            ? $competition->reviews()->whereNotIn('submission_reviews.reviewer_id', $ids)->exists()
            : false;

        if ($assigned) {
            throw ValidationException::withMessages(['reviewers' => __('competitions.validation.reviewer_assigned')]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function syncCriteria(Competition $competition, array $rows): void
    {
        $kept = [];

        foreach (array_values($rows) as $index => $row) {
            $criterion = $this->existing($competition->criteria(), $row['id'] ?? null) ?? new CompetitionCriterion;
            $criterion->fill([
                'name' => $row['name'],
                'weight' => $row['weight'],
                'max_score' => $row['max_score'],
                'sort_order' => $index + 1,
            ]);
            $competition->criteria()->save($criterion);
            $kept[] = $criterion->id;
        }

        $competition->criteria()->whereKeyNot($kept)->get()->each->delete();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function syncQuestions(Competition $competition, array $rows): void
    {
        $kept = [];

        foreach (array_values($rows) as $index => $row) {
            $type = self::type($row['type']) ?? throw new \LogicException('Validated question type');
            $question = $this->existing($competition->questions(), $row['id'] ?? null) ?? new CompetitionQuestion;
            $options = $type === QuestionType::MultipleChoice ? array_values($row['options'] ?? []) : [];

            $question->fill([
                'type' => $type,
                'prompt' => $row['prompt'],
                'is_required' => (bool) ($row['is_required'] ?? true),
                'options' => $options === [] ? null : array_map(fn (array $option, int $position): array => ['key' => self::OPTION_KEYS[$position], 'label' => trim($option['label'])], $options, array_keys($options)),
                'correct_option' => $this->correctKey($options),
                'points' => $type === QuestionType::MultipleChoice ? (int) ($row['points'] ?? 0) : 0,
                'max_video_seconds' => $type === QuestionType::Video ? $row['max_video_seconds'] : null,
                'max_video_mb' => $type === QuestionType::Video ? $row['max_video_mb'] : null,
                'sort_order' => $index + 1,
                'context_text' => filled($row['context_text'] ?? null) ? trim((string) $row['context_text']) : null,
                'media_type' => filled($row['media_path'] ?? null) ? ($row['media_type'] ?? null) : null,
                'media_path' => filled($row['media_path'] ?? null) ? $row['media_path'] : null,
            ]);
            $competition->questions()->save($question);
            $kept[] = $question->id;
        }

        $competition->questions()->whereKeyNot($kept)->get()->each->delete();
    }

    /**
     * النوع من اللوحة حالة من التعداد، ومن غيرها نص.
     */
    private static function type(mixed $value): ?QuestionType
    {
        return $value instanceof QuestionType ? $value : QuestionType::tryFrom(is_string($value) ? $value : '');
    }

    /**
     * @param  list<array<string, mixed>>  $options
     */
    private function correctKey(array $options): ?string
    {
        foreach ($options as $position => $option) {
            if ($option['is_correct'] ?? false) {
                return self::OPTION_KEYS[$position];
            }
        }

        return null;
    }

    /**
     * سجل المسابقة نفسها بمعرّفه، أو null لسجل جديد أو معرّف من غيرها.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  HasMany<TModel, Competition>  $relation
     * @return TModel|null
     */
    private function existing($relation, mixed $id)
    {
        return is_numeric($id) && $relation->getParent()->exists ? $relation->clone()->whereKey((int) $id)->first() : null;
    }
}
