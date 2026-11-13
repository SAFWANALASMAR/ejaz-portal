<?php

namespace App\Console\Commands;

use App\Actions\Competitions\RegisterStudent;
use App\Actions\Competitions\StartAttempt;
use App\Actions\Competitions\SubmitAttempt;
use App\Enums\PassageKind;
use App\Enums\QuestionType;
use App\Enums\RoleName;
use App\Models\Competition;
use App\Models\CompetitionAttempt;
use App\Models\CompetitionCategory;
use App\Models\CompetitionPassage;
use App\Models\CompetitionQuestion;
use App\Models\CompetitionRegion;
use App\Models\CompetitionRetake;
use App\Models\CompetitionRound;
use App\Models\CompetitionSubmission;
use App\Models\User;
use App\Support\CompetitionShortLink;
use App\Support\RoundQuestions;
use Database\Seeders\BrahinCompetitionSeeder;
use Database\Seeders\CompetitionListsSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * مسابقة طلاب تجريبية لاختبارات المتصفح ولقطاتها (tests/browser/student-competition.spec.mjs)، للبيئة المحلية
 * وحدها: كل نص فيها معلَّم «تجريبي» ولا يُنشر في غيرها. مرحلة سابقة منشورة النتائج فيها محاولة لكل طالب تجريبي،
 * ومرحلة جارية ببنك من كل الأنواع، وطالب تجريبي لكل عرض لم يسجل في المسابقة بعد (يسجل في الاختبار نفسه)
 * بكلمة مرور عشوائية لهذا التشغيل. وإعادة التشغيل تبدأ من جديد.
 */
#[Signature('ejaz:demo-student-competition {--json : إخراج البيانات بصيغة JSON}')]
#[Description('إنشاء مسابقة طلاب تجريبية لاختبارات المتصفح (البيئة المحلية فقط)')]
class CreateDemoStudentCompetition extends Command
{
    public const SLUG = 'demo-students';

    public const STUDENTS = ['desktop', 'tablet', 'mobile'];

    public function handle(RegisterStudent $register, StartAttempt $start, SubmitAttempt $submit): int
    {
        if (! $this->laravel->environment('local', 'testing')) {
            $this->error('هذا الأمر للبيئة المحلية فقط.');

            return self::FAILURE;
        }

        (new CompetitionListsSeeder)->run();
        $password = Str::password(20);
        /** @var array{page_content: array<string, mixed>, prize_table: list<array{male: int, female: int}>, rounds: list<array{name: string, branch: string}>} $brochure */
        $brochure = require database_path(BrahinCompetitionSeeder::DATA);

        $competition = DB::transaction(function () use ($brochure): Competition {
            Competition::withTrashed()->where('slug', self::SLUG)->get()->each(function (Competition $old): void {
                CompetitionAttempt::query()->whereIn('round_id', $old->rounds()->select('id'))->delete();
                CompetitionRetake::query()->whereIn('round_id', $old->rounds()->select('id'))->delete();
                $old->submissions()->withTrashed()->forceDelete();
                $old->forceDelete();
            });

            $competition = new Competition([
                'slug' => self::SLUG,
                'title' => 'مسابقة تجريبية للطلاب (للاختبار)',
                'description' => 'وصف تجريبي لاختبار الواجهة، لا محتوى للجمعية.',
                'opens_at' => now()->subDay(),
                'closes_at' => now()->addWeek(),
                'is_published' => true,
                'student_mode' => true,
                'categories' => [],
                // نصوص المطوية وجوائزها لعرض أقسام الصفحة ودليل الدخول في اللقطات (BrahinCompetitionSeeder)
                'page_content' => $brochure['page_content'],
                'prize_table' => $brochure['prize_table'],
            ]);
            $competition->save();
            $competition->openCategories()->sync(CompetitionCategory::query()->pluck('id')->all());
            // الفئات المربوطة تدخل في الدرجات القصوى المخزنة لكل مرحلة (AttemptLayout::mixedMaxScores)
            RoundQuestions::forgetCompetition($competition->id);
            CompetitionShortLink::assign($competition);

            return $competition;
        });

        $past = $this->round($competition, 'مرحلة تجريبية سابقة', now()->subDays(3), now()->subDays(2), 1);
        $current = $this->round($competition, 'المرحلة التجريبية الحالية', now()->subHour(), now()->addHours(6), 2);
        $past->forceFill(['branch' => $brochure['rounds'][0]['branch']])->save();
        $current->forceFill(['branch' => $brochure['rounds'][1]['branch']])->save();
        $this->bank($competition, $past, 3);
        $this->bank($competition, $current, 0);

        $students = [];

        foreach (self::STUDENTS as $index => $width) {
            $user = User::query()->firstOrNew(['email' => 'demo.student.'.$width.'@ejaz.test']);
            $user->forceFill(['name' => 'طالب تجريبي '.$width, 'phone' => null, 'password' => $password, 'email_verified_at' => now(), 'phone_verified_at' => null, 'privacy_accepted_at' => now(), 'is_active' => true])->save();
            $user->syncRoles([RoleName::Member->value]);
            $students[$width] = $user->email;
        }

        // طالب تجريبي سجّل وسلّم في المرحلة السابقة ونُشرت نتيجتها، لعرض الدرجة في «مشاركاتي» وفي لوحة النتائج
        $veteran = User::query()->firstOrNew(['email' => 'demo.student.veteran@ejaz.test']);
        $veteran->forceFill(['name' => 'طالب تجريبي سابق', 'phone' => null, 'password' => $password, 'email_verified_at' => now(), 'privacy_accepted_at' => now(), 'is_active' => true])->save();
        $veteran->syncRoles([RoleName::Member->value]);
        $this->travelInto($past, function () use ($register, $start, $submit, $competition, $past, $veteran): void {
            $register->handle($veteran, $competition, [
                'full_name' => 'طالب تجريبي سابق بن مثال',
                'national_id' => RegisterLoadStudents::nationalId(9000001),
                'category_id' => CompetitionCategory::query()->where('name', 'المرحلة الثانوية')->value('id'),
                'grade' => 'الثالث الثانوي',
                'region_id' => CompetitionRegion::query()->active()->value('id'),
                'gender' => 'male',
                'guardian_phone' => '0550000000',
                'pledge' => '1',
            ]);
            $attempt = $start->handle($veteran, $past);
            $attempt->forceFill(['answers' => collect($attempt->questionIds())->mapWithKeys(fn (int $id): array => [(string) $id => 'b'])->all()])->save();
            $submit->handle($veteran, $attempt);
        });
        $past->forceFill(['results_published_at' => now()->subDay()])->save();
        $students['veteran'] = $veteran->email;

        // المشرف التجريبي (ejaz:demo-user --admin) يجرّب رحلة المدير التنفيذي ومنها الاطلاع الكامل على رقم الهوية
        User::query()->where('email', CreateDemoUser::ADMIN_EMAIL)->first()?->assignRole(RoleName::ExecutiveDirector->value);

        $data = ['slug' => self::SLUG, 'round' => $current->uuid, 'password' => $password, 'students' => $students];

        $this->option('json') ? $this->line((string) json_encode($data)) : $this->info((string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }

    private function round(Competition $competition, string $name, mixed $starts, mixed $ends, int $sort): CompetitionRound
    {
        $round = new CompetitionRound(['name' => $name, 'sort_order' => $sort, 'starts_at' => $starts, 'ends_at' => $ends, 'question_count' => 6, 'duration_minutes' => 30]);
        $round->competition_id = $competition->id;
        $round->save();

        return $round;
    }

    /**
     * بنك تجريبي من كل الأنواع: اختياران، وصح وخطأ، ونص بسؤالين، ومقطع بسؤال.
     */
    private function bank(Competition $competition, CompetitionRound $round, int $seed): void
    {
        $text = $this->passage($competition, $round, PassageKind::Text, ['title' => 'نص تجريبي للقراءة', 'body' => str_repeat("هذه فقرة تجريبية لاختبار عرض النص المقروء فوق أسئلته في صفحة واحدة، وليست من محتوى الجمعية.\n", 6)]);
        $video = $this->passage($competition, $round, PassageKind::Video, ['title' => 'مقطع تجريبي', 'video_url' => 'https://www.youtube.com/watch?v=aqz-KE-bpKQ', 'body' => 'ملاحظة تجريبية تحت المقطع.']);

        $choices = fn (string $prefix): array => array_map(fn (string $key, string $label): array => ['key' => $key, 'label' => $prefix.' '.$label], ['a', 'b', 'c', 'd'], ['أ', 'ب', 'ج', 'د']);

        foreach ([
            ['type' => QuestionType::MultipleChoice, 'prompt' => 'سؤال اختيار تجريبي أول؟', 'options' => $choices('خيار تجريبي')],
            ['type' => QuestionType::MultipleChoice, 'prompt' => 'سؤال اختيار تجريبي ثانٍ؟', 'options' => $choices('إجابة تجريبية')],
            ['type' => QuestionType::TrueFalse, 'prompt' => 'عبارة تجريبية للصح والخطأ.', 'options' => [['key' => 't', 'label' => 'صح'], ['key' => 'f', 'label' => 'خطأ']], 'correct_option' => 't'],
            ['type' => QuestionType::PassageChoice, 'prompt' => 'سؤال تجريبي أول على النص؟', 'options' => $choices('خيار'), 'passage_id' => $text->id],
            ['type' => QuestionType::PassageChoice, 'prompt' => 'سؤال تجريبي ثانٍ على النص؟', 'options' => $choices('خيار'), 'passage_id' => $text->id],
            ['type' => QuestionType::VideoChoice, 'prompt' => 'سؤال تجريبي على المقطع؟', 'options' => $choices('خيار'), 'passage_id' => $video->id],
        ] as $order => $attributes) {
            $question = new CompetitionQuestion(['correct_option' => 'b', 'points' => 1, 'is_required' => false, 'sort_order' => $order + 1 + $seed, ...$attributes, 'round_id' => $round->id]);
            $question->competition_id = $competition->id;
            $question->save();
        }

        RoundQuestions::forget($round->id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function passage(Competition $competition, CompetitionRound $round, PassageKind $kind, array $attributes): CompetitionPassage
    {
        $passage = new CompetitionPassage(['round_id' => $round->id, 'kind' => $kind, ...$attributes]);
        $passage->competition_id = $competition->id;
        $passage->save();

        return $passage;
    }

    /**
     * التسجيل والمحاولة في نافذة المرحلة السابقة: تُفتح مؤقتاً للحظة ثم تُعاد.
     */
    private function travelInto(CompetitionRound $round, callable $callback): void
    {
        $window = [$round->starts_at, $round->ends_at];
        $round->forceFill(['starts_at' => now()->subMinute(), 'ends_at' => now()->addHour()])->save();

        try {
            $callback();
        } finally {
            $round->forceFill(['starts_at' => $window[0], 'ends_at' => $window[1]])->save();
            CompetitionSubmission::query()->where('competition_id', $round->competition_id)->update(['registered_at' => $window[0]]);
        }
    }
}
