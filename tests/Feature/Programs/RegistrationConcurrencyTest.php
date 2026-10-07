<?php

use App\Actions\Programs\RegisterForProgram;
use App\Enums\ProgramDelivery;
use App\Enums\RegistrationStatus;
use App\Models\Program;
use App\Models\ProgramRegistration;
use App\Models\ProgramType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/*
| شرط القبول (requirements/03): تسجيلان متزامنان على آخر مقعد لا يقبلان معاً.
|
| تزامن حقيقي: عمليات منفصلة (pcntl_fork)، لكل منها اتصالها بقاعدة البيانات، تبدأ في اللحظة نفسها.
| ولأن معاملة RefreshDatabase لا تراها اتصالات أخرى، تُنشأ البيانات وتُعتمد عبر اتصال مستقل،
| وتُحذف في آخر الاختبار.
*/

it('accepts only one of several simultaneous registrations for the last seat', function () {
    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('pcntl غير متاح');
    }

    Notification::fake();
    config([
        'database.connections.committed' => config('database.connections.pgsql'),
        'database.connections.racer' => config('database.connections.pgsql'),
    ]);

    $racers = 6;
    $type = ProgramType::on('committed')->create(['name' => 'تزامن', 'slug' => 'race-type-'.uniqid()]);
    $program = Program::on('committed')->forceCreate([
        'program_type_id' => $type->id, 'slug' => 'race-'.uniqid(), 'title' => 'آخر مقعد',
        'starts_at' => now()->addDays(3), 'delivery' => ProgramDelivery::InPerson, 'seats' => 1, 'is_published' => true,
    ]);
    $users = collect(range(1, $racers))->map(fn () => User::factory()->connection('committed')->create());

    $results = sys_get_temp_dir().'/race-'.uniqid();
    mkdir($results);
    $start = microtime(true) + 1.0;
    $children = [];

    try {
        foreach ($users as $index => $user) {
            $pid = pcntl_fork();

            if ($pid === 0) {
                // اتصال جديد للعملية الفرعية، ولا يُغلق اتصال الأصل المشترك معها
                DB::setDefaultConnection('racer');
                time_sleep_until($start);

                try {
                    app(RegisterForProgram::class)->handle(User::findOrFail($user->id), Program::findOrFail($program->id), []);
                    $outcome = 'ok';
                } catch (ValidationException) {
                    $outcome = 'full';
                } catch (Throwable $exception) {
                    $outcome = 'error: '.$exception->getMessage();
                }

                file_put_contents($results.'/'.$index, $outcome);
                posix_kill(posix_getpid(), SIGKILL);
            }

            $children[] = $pid;
        }

        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
        }

        $outcomes = collect(glob($results.'/*'))->map(fn (string $file) => file_get_contents($file))->countBy()->all();

        expect($outcomes)->toBe(['full' => $racers - 1, 'ok' => 1]);
        expect(ProgramRegistration::on('committed')->where('program_id', $program->id)->where('status', RegistrationStatus::Confirmed->value)->count())->toBe(1);
    } finally {
        array_map('unlink', glob($results.'/*'));
        rmdir($results);
        ProgramRegistration::on('committed')->where('program_id', $program->id)->delete();
        Program::on('committed')->whereKey($program->id)->forceDelete();
        $type->delete();
        User::on('committed')->whereIn('id', $users->pluck('id'))->delete();
        DB::purge('committed');
    }
});
