<?php

use App\Actions\Competitions\PublishRoundResults;
use App\Actions\Competitions\SaveCompetitionRound;
use App\Actions\Competitions\StartAttempt;
use App\Enums\AttemptStatus;
use App\Enums\RoleName;
use App\Models\Competition;
use App\Models\CompetitionAttempt;
use App\Models\CompetitionRound;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/*
| «نشر النتائج» ومدّ نهاية المرحلة في اللحظة نفسها (المراجعة النهائية، M-B): يُقبل أحدهما وحده. إن سبق المدّ رأى النشرُ
| النهايةَ الجديدة فرفض، وإن سبق النشرُ رُفض المدّ لقفل النافذة. ولا تُنشر مرحلة بنهاية ممدودة، ولا تُسلَّم محاولة مُدّ وقتها.
|
| تزامن حقيقي: عمليتان منفصلتان (pcntl_fork) لكل منهما اتصالها، كاختبار آخر مقعد في البرامج. والبيانات تُعتمد عبر اتصال
| مستقل (معاملة RefreshDatabase لا تراها الاتصالات الأخرى)، وتُحذف في آخر الاختبار بمعرّفاتها بعد ما كان قبله.
*/

it('accepts only one of a simultaneous publish and end extension, never publishing an extended round', function () {
    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('pcntl غير متاح');
    }

    config([
        'competitions.exam.national_id_key' => 'test-hmac-key',
        'competitions.student.min_questions' => 1,
        'database.connections.committed' => config('database.connections.pgsql'),
        'database.connections.racer' => config('database.connections.pgsql'),
    ]);

    $tables = ['activity_log', 'competition_attempts', 'competition_submissions', 'competition_questions', 'competition_rounds', 'competitions', 'competition_categories', 'competition_regions', 'users'];
    $before = collect($tables)->mapWithKeys(fn (string $table) => [$table => (int) DB::connection('committed')->table($table)->max('id')])->all();
    $results = sys_get_temp_dir().'/publish-race-'.uniqid();
    mkdir($results);
    $default = DB::getDefaultConnection();

    try {
        DB::setDefaultConnection('committed');

        $director = userWithRole(RoleName::ExecutiveDirector);
        $competition = Competition::factory()->student()->create(['slug' => 'race-'.uniqid()]);
        $round = $competition->rounds()->firstOrFail();
        studentQuestion($round);

        // طالب بدأ قبل النهاية، وانتهت المرحلة ووقته وهامش الشبكة: النشر يسلّم محاولته، والمدّ يعيده إلى وقته
        $this->travelTo($round->starts_at->copy()->addMinutes(10));
        $attempt = app(StartAttempt::class)->handle(registeredStudent($competition), $round->fresh());
        $round->forceFill(['ends_at' => now()->addMinutes(5)])->save();
        $attempt->forceFill(['deadline_at' => $round->ends_at])->save();
        $this->travelTo($round->ends_at->copy()->addMinute());
        $round = $round->fresh();

        $operations = [
            'publish' => fn () => app(PublishRoundResults::class)->handle(User::findOrFail($director->id), CompetitionRound::findOrFail($round->id)),
            'extend' => fn () => app(SaveCompetitionRound::class)->handle(User::findOrFail($director->id), Competition::findOrFail($competition->id), CompetitionRound::findOrFail($round->id), [
                'name' => $round->name, 'starts_at' => $round->starts_at, 'ends_at' => now()->addHour(),
                'question_count' => $round->question_count, 'duration_minutes' => $round->duration_minutes,
            ]),
        ];

        $start = microtime(true) + 1.0;
        $children = [];

        foreach ($operations as $name => $operation) {
            $pid = pcntl_fork();

            if ($pid === 0) {
                DB::setDefaultConnection('racer');
                time_sleep_until($start);

                try {
                    $operation();
                    $outcome = 'ok';
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

        $outcomes = collect(['publish', 'extend'])->mapWithKeys(fn (string $name) => [$name => (string) @file_get_contents($results.'/'.$name)])->all();
        $round = CompetitionRound::findOrFail($round->id);
        $attempt = CompetitionAttempt::findOrFail($attempt->id);

        expect(collect($outcomes)->filter(fn (string $outcome) => $outcome === 'ok')->count())->toBe(1)
            ->and(collect($outcomes)->filter(fn (string $outcome) => $outcome === 'refused')->count())->toBe(1);

        if ($outcomes['publish'] === 'ok') {
            expect($round->resultsPublished())->toBeTrue()
                ->and($round->ends_at->isPast())->toBeTrue()
                ->and($attempt->status)->toBe(AttemptStatus::Submitted);
        } else {
            expect($round->resultsPublished())->toBeFalse()
                ->and($round->ends_at->isFuture())->toBeTrue()
                ->and($attempt->status)->toBe(AttemptStatus::InProgress)
                ->and($attempt->deadline_at->isFuture())->toBeTrue();
        }
    } finally {
        DB::setDefaultConnection($default);
        array_map('unlink', glob($results.'/*') ?: []);
        rmdir($results);

        $committed = DB::connection('committed');
        $users = $committed->table('users')->where('id', '>', $before['users'])->pluck('id');
        $committed->table('model_has_roles')->where('model_type', (new User)->getMorphClass())->whereIn('model_id', $users)->delete();

        foreach ($tables as $table) {
            $committed->table($table)->where('id', '>', $before[$table])->delete();
        }

        DB::purge('committed');
    }
});
