<?php

use App\Actions\Competitions\SaveStudentQuestion;
use App\Actions\Competitions\StartAttempt;
use App\Enums\RoleName;
use App\Models\Competition;
use App\Models\CompetitionAttempt;
use App\Models\CompetitionCategory;
use App\Models\CompetitionRound;
use App\Models\User;
use App\Support\AttemptLayout;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Validation\ValidationException;

/*
| «ابدأ الاختبار» لطلاب كثيرين في اللحظة نفسها (تحسين أداء البدء، اختبار التحمل): ضمانات البدء بعد التحسين. محاولة واحدة
| لكل طالب في المرحلة ولو نقر مرتين معاً، وأرقام مرجعية لا تتكرر، والعدد والدرجة القصوى واحدة للجميع. وإضافة سؤال في
| لحظة البدء: إما تسبق البدء كله، وإما تُرفض لقفل البنك، ولا تبقى جاهزية البنك المخزّنة قديمة بعدها.
|
| تزامن حقيقي كاختبار النشر والمدّ (PublishRaceTest): عمليات منفصلة (pcntl_fork) لكل منها اتصالها بالقاعدة، والذاكرة
| المؤقتة في Redis مشتركة بينها كالإنتاج (بادئة خاصة بالاختبار تُمسح في آخره). والبيانات تُعتمد عبر اتصال مستقل، وتُحذف
| في آخر الاختبار بمعرّفاتها بعد ما كان قبله.
*/

/**
 * يشغّل كل عملية في عملية منفصلة تبدأ في اللحظة نفسها، ويعيد نتيجة كل منها: معرّف المحاولة، أو refused، أو الخطأ.
 *
 * @param  array<string, Closure(): mixed>  $operations
 * @return array<string, string>
 */
function raceStarts(array $operations): array
{
    $results = sys_get_temp_dir().'/start-race-'.uniqid();
    mkdir($results);
    $start = microtime(true) + 1.0;
    $children = [];

    foreach ($operations as $name => $operation) {
        $pid = pcntl_fork();

        if ($pid === 0) {
            // اتصال القاعدة وRedis لكل عملية، لا مقبس الأب المنسوخ
            DB::setDefaultConnection('racer');
            Redis::purge('cache');
            Redis::purge();
            time_sleep_until($start);

            try {
                $result = $operation();
                $outcome = $result instanceof CompetitionAttempt ? 'attempt:'.$result->id : 'ok';
            } catch (ValidationException) {
                $outcome = 'refused';
            } catch (Throwable $exception) {
                $outcome = 'error: '.$exception->getMessage();
            }

            file_put_contents($results.'/'.$name, $outcome);
            posix_kill(posix_getpid(), SIGKILL);
        }

        $children[] = $pid;
    }

    foreach ($children as $pid) {
        pcntl_waitpid($pid, $status);
    }

    Redis::purge('cache');
    Redis::purge();

    $outcomes = collect(array_keys($operations))->mapWithKeys(fn (string $name) => [$name => (string) @file_get_contents($results.'/'.$name)])->all();
    array_map('unlink', glob($results.'/*') ?: []);
    rmdir($results);

    return $outcomes;
}

beforeEach(function () {
    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('pcntl غير متاح');
    }

    config([
        'competitions.exam.national_id_key' => 'test-hmac-key',
        'competitions.student.min_questions' => 1,
        'database.connections.committed' => config('database.connections.pgsql'),
        'database.connections.racer' => config('database.connections.pgsql'),
        'cache.default' => 'redis',
        'cache.prefix' => 'start-race-'.uniqid().'-',
    ]);

    $this->tables = ['activity_log', 'competition_attempts', 'competition_submissions', 'competition_questions', 'competition_rounds', 'competitions', 'competition_categories', 'competition_regions', 'users'];
    $this->before = collect($this->tables)->mapWithKeys(fn (string $table) => [$table => (int) DB::connection('committed')->table($table)->max('id')])->all();
    $this->default = DB::getDefaultConnection();
    DB::setDefaultConnection('committed');
});

afterEach(function () {
    DB::setDefaultConnection($this->default);

    $committed = DB::connection('committed');
    $users = $committed->table('users')->where('id', '>', $this->before['users'])->pluck('id');
    $committed->table('model_has_roles')->where('model_type', (new User)->getMorphClass())->whereIn('model_id', $users)->delete();

    foreach ($this->tables as $table) {
        $committed->table($table)->where('id', '>', $this->before[$table])->delete();
    }

    DB::purge('committed');

    $prefix = (string) config('cache.prefix');
    $connection = Cache::store('redis')->connection();
    $keys = $connection->keys($prefix.'*');

    foreach ($keys as $key) {
        // keys يعيد المفاتيح ببادئة اتصال Redis إن وُجدت، وdel يضيفها من جديد
        $connection->del(str_replace((string) config('database.redis.options.prefix'), '', $key));
    }
});

it('gives each of many students starting at the same moment exactly one attempt, even when each clicks twice', function () {
    $competition = Competition::factory()->student()->create(['slug' => 'start-race-'.uniqid()]);
    $round = $competition->rounds()->firstOrFail();
    collect(range(1, 8))->each(fn () => studentQuestion($round));
    $round->forceFill(['question_count' => 5])->save();
    $this->travelTo($round->starts_at->copy()->addMinutes(5));
    $students = collect(range(1, 6))->map(fn (int $serial) => registeredStudent($competition, $serial));

    $operations = [];

    foreach ($students as $student) {
        foreach (['a', 'b'] as $click) {
            $operations[$student->id.$click] = fn () => app(StartAttempt::class)->handle(User::findOrFail($student->id), CompetitionRound::findOrFail($round->id));
        }
    }

    $outcomes = raceStarts($operations);
    $attempts = CompetitionAttempt::query()->where('round_id', $round->id)->get();

    expect(collect($outcomes)->filter(fn (string $outcome) => ! str_starts_with($outcome, 'attempt:'))->all())->toBe([])
        ->and($attempts)->toHaveCount(6)
        ->and($attempts->pluck('submission_id')->unique())->toHaveCount(6)
        ->and($attempts->pluck('reference')->unique())->toHaveCount(6)
        ->and($attempts->pluck('question_count')->unique()->all())->toBe([5])
        ->and($attempts->map(fn (CompetitionAttempt $attempt) => (float) $attempt->max_score)->unique()->all())->toBe([5.0]);

    // النقرتان لكل طالب أعادتا المحاولة نفسها، بخمسة أسئلة مختلفة من بنكه
    foreach ($students as $student) {
        $attempt = $attempts->firstWhere('user_id', $student->id);

        expect($outcomes[$student->id.'a'])->toBe('attempt:'.$attempt->id)
            ->and($outcomes[$student->id.'b'])->toBe('attempt:'.$attempt->id)
            ->and(collect($attempt->questionIds())->unique())->toHaveCount(5);
    }
});

it('either adds a question before every start or refuses it for the locked bank, never leaving a stale bank readiness', function () {
    $director = userWithRole(RoleName::ExecutiveDirector);
    $competition = Competition::factory()->student()->create(['slug' => 'start-race-'.uniqid()]);
    $round = $competition->rounds()->firstOrFail();
    collect(range(1, 3))->each(fn () => studentQuestion($round));
    $this->travelTo($round->starts_at->copy()->addMinutes(5));
    $students = collect(range(1, 3))->map(fn (int $serial) => registeredStudent($competition, $serial));
    $category = (int) CompetitionCategory::query()->where('name', 'المرحلة الثانوية')->value('id');

    // جاهزية البنك مخزّنة قبل السباق: ثلاثة أسئلة لعدد ثلاثة
    expect(AttemptLayout::shortfall($round->fresh(), $category))->toBeNull();

    // والسؤال المضاف بدرجتين يجعل البنك أكبر من العدد بدرجات مختلفة، فغير جاهز (-1): بدءٌ بعده يُرفض، وجاهزية قديمة
    // مخزّنة كانت ستقبله
    $operations = $students->mapWithKeys(fn (User $student) => ['student'.$student->id => fn () => app(StartAttempt::class)->handle(User::findOrFail($student->id), CompetitionRound::findOrFail($round->id))])->all();
    $operations['question'] = fn () => app(SaveStudentQuestion::class)->handle(User::findOrFail($director->id), CompetitionRound::findOrFail($round->id), null, [
        'type' => 'multiple_choice', 'prompt' => 'سؤال السباق', 'points' => 2, 'options' => [['label' => 'أ', 'is_correct' => true], ['label' => 'ب']],
    ]);

    $outcomes = raceStarts($operations);
    $starts = collect($outcomes)->except('question');
    $attempts = CompetitionAttempt::query()->where('round_id', $round->id)->get();

    expect(collect($outcomes)->filter(fn (string $outcome) => str_starts_with($outcome, 'error'))->all())->toBe([])
        ->and($outcomes['question'])->toBeIn(['ok', 'refused']);

    if ($outcomes['question'] === 'ok') {
        // سبق السؤالُ البدءَ كله: لا محاولة، وكل بدء رأى البنك الجديد فرُفض
        expect($round->questions()->count())->toBe(4)
            ->and($attempts)->toHaveCount(0)
            ->and($starts->unique()->all())->toBe(['refused'])
            ->and(AttemptLayout::shortfall($round->fresh(), $category))->toBe(-1);
    } else {
        // سبق بدءٌ السؤالَ فقُفل البنك: بدأ الثلاثة بالأسئلة الثلاثة، والجاهزية كما كانت
        expect($round->questions()->count())->toBe(3)
            ->and($attempts)->toHaveCount(3)
            ->and($attempts->pluck('question_count')->unique()->all())->toBe([3])
            ->and(AttemptLayout::shortfall($round->fresh(), $category))->toBeNull();
    }
});
