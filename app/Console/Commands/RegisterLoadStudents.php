<?php

namespace App\Console\Commands;

use App\Actions\Competitions\RegisterStudent;
use App\Enums\PassageKind;
use App\Enums\QuestionType;
use App\Models\Competition;
use App\Models\CompetitionCategory;
use App\Models\CompetitionPassage;
use App\Models\CompetitionQuestion;
use App\Models\CompetitionRegion;
use App\Models\CompetitionRound;
use App\Models\User;
use App\Support\RoundQuestions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * تسجيل حسابات اختبار التحمل (ejaz:load-accounts) في مسابقة طلاب، بأرقام هوية صحيحة الصيغة متسلسلة، عبر
 * إجراء التسجيل نفسه (tests/load/README.md — «مسابقة الطلاب»). للبيئة المحلية والتجريبية فقط.
 *
 * الطلاب موزّعون على الفئات المفتوحة كلها بالتناوب، و`--bank` يبذر في المرحلة `--round` بنكاً تجريبياً لكل فئة،
 * فيقيس البدء والصفحات حمولة البنك الحقيقية بفئاتها الأربع لا بنك فئة واحدة (مراجعة 26 أكتوبر، م٣).
 */
#[Signature('ejaz:load-students {competition : رابط المسابقة} {count : عدد الطلاب}
    {--round= : معرّف المرحلة (uuid) التي يُبذر فيها البنك}
    {--bank=0 : عدد أسئلة البنك التجريبي لكل فئة مفتوحة}
    {--questions=20 : عدد أسئلة المرحلة لكل طالب مع البنك}')]
#[Description('تسجيل حسابات اختبار التحمل في مسابقة طلاب (المحلية والتجريبية فقط)')]
class RegisterLoadStudents extends Command
{
    public function handle(RegisterStudent $register): int
    {
        if (! $this->laravel->environment('local', 'testing', 'staging')) {
            $this->error('هذا الأمر للبيئة المحلية والتجريبية فقط.');

            return self::FAILURE;
        }

        $competition = Competition::query()->where('slug', (string) $this->argument('competition'))->where('student_mode', true)->first();

        if ($competition === null) {
            $this->error('لا مسابقة طلاب بهذا الرابط.');

            return self::FAILURE;
        }

        $categories = $competition->openCategories()->get()->values();
        $region = CompetitionRegion::query()->active()->value('id');
        $count = (int) $this->argument('count');
        $registered = 0;

        if ($categories->isEmpty() || $region === null) {
            $this->error('المسابقة بلا فئة مفتوحة، أو لا منطقة مفعّلة.');

            return self::FAILURE;
        }

        if ((int) $this->option('bank') > 0) {
            $round = $competition->rounds()->where('uuid', (string) $this->option('round'))->first();

            if ($round === null || $round->attempts()->exists()) {
                $this->error('المرحلة غير موجودة، أو بدأت فيها محاولة فالبنك مقفل.');

                return self::FAILURE;
            }

            $this->bank($competition, $round, $categories->all(), (int) $this->option('bank'));
        }

        for ($n = 1; $n <= $count; $n++) {
            $user = User::query()->where('email', 'load-'.$n.'@'.CreateLoadAccounts::DOMAIN)->first();

            if ($user === null || $competition->submissions()->where('user_id', $user->id)->exists()) {
                continue;
            }

            /** @var CompetitionCategory $category */
            $category = $categories[$n % $categories->count()];

            $register->handle($user, $competition, [
                'full_name' => 'طالب اختبار التحمل بن مثال',
                'national_id' => self::nationalId(100000 + $n),
                'category_id' => $category->id,
                'grade' => $category->gradeList()[0] ?? null,
                'region_id' => $region,
                'gender' => $n % 2 === 0 ? 'female' : 'male',
                'pledge' => '1',
            ]);
            $registered++;
        }

        $this->info((string) $registered);

        return self::SUCCESS;
    }

    /**
     * رقم بصيغة الهوية ورقم تحققها من رقم تسلسلي، في مدى يبدأ بـ 19 لا يُتوقع لأشخاص في البيئة التجريبية.
     */
    public static function nationalId(int $serial): string
    {
        $body = '19'.str_pad((string) $serial, 7, '0', STR_PAD_LEFT);
        $sum = 0;

        foreach (str_split($body) as $position => $digit) {
            $number = (int) $digit * ($position % 2 === 0 ? 2 : 1);
            $sum += $number > 9 ? $number - 9 : $number;
        }

        return $body.((10 - $sum % 10) % 10);
    }

    /**
     * لكل فئة: نص قراءة بأربعة أسئلة، والباقي اختيار من متعدد مع صح وخطأ كل خامس سؤال، بدرجة واحدة لكل سؤال
     * فيصح السحب من بنك أكبر من العدد. نصوص تجريبية معلَّمة، لا محتوى للجمعية.
     *
     * @param  list<CompetitionCategory>  $categories
     */
    private function bank(Competition $competition, CompetitionRound $round, array $categories, int $perCategory): void
    {
        $choices = array_map(fn (string $key): array => ['key' => $key, 'label' => 'خيار تجريبي لاختبار التحمل '.$key], ['a', 'b', 'c', 'd']);
        $trueFalse = [['key' => 't', 'label' => 'صح'], ['key' => 'f', 'label' => 'خطأ']];

        foreach ($categories as $category) {
            $passage = new CompetitionPassage(['round_id' => $round->id, 'category_id' => $category->id, 'kind' => PassageKind::Text, 'title' => 'نص تجريبي لاختبار التحمل', 'body' => str_repeat("فقرة تجريبية لقياس حمولة بنك المرحلة، وليست من محتوى الجمعية.\n", 8)]);
            $passage->competition_id = $competition->id;
            $passage->save();

            for ($i = 1; $i <= $perCategory; $i++) {
                $inPassage = $i <= 4;
                $type = match (true) {
                    $inPassage => QuestionType::PassageChoice,
                    $i % 5 === 0 => QuestionType::TrueFalse,
                    default => QuestionType::MultipleChoice,
                };
                $question = new CompetitionQuestion([
                    'type' => $type,
                    'prompt' => 'سؤال تجريبي '.$i.' لاختبار التحمل في فئة '.$category->name.'؟',
                    'options' => $type === QuestionType::TrueFalse ? $trueFalse : $choices,
                    'correct_option' => $type === QuestionType::TrueFalse ? 't' : 'b',
                    'points' => 1,
                    'is_required' => false,
                    'sort_order' => $i,
                    'round_id' => $round->id,
                    'category_id' => $category->id,
                    'passage_id' => $inPassage ? $passage->id : null,
                ]);
                $question->competition_id = $competition->id;
                $question->save();
            }
        }

        $round->forceFill(['question_count' => (int) $this->option('questions')])->save();
        RoundQuestions::forget($round->id);
    }
}
