<?php

use App\Actions\Competitions\StartAttempt;
use App\Actions\Competitions\SubmitAttempt;
use App\Contracts\ProgramStatsSource;
use App\Enums\RoleName;
use App\Filament\Widgets\ProgramStatsByTypeWidget;
use App\Models\AssemblyMembership;
use App\Models\Competition;
use App\Models\CompetitionSubmission;
use App\Models\Program;
use App\Models\ProgramRegistration;
use App\Models\ProgramType;
use App\Models\User;
use App\Support\HomePortalStats;
use App\Support\ProgramStats\CompetitionsSource;
use App\Support\ProgramStats\ProgramTypesSource;
use App\Support\ProgramStatsByType;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

/*
| «إحصائيات البرامج لكل نوع» (البند R23 — docs/decisions/2026-11-02-program-stats-by-type.md): مصدر لكل نوع يُسجَّل في
| config/program-stats.php، ومجاميع مخزّنة تُحسب مع ejaz:refresh-stats، في لوحة الإحصائيات وبوابة المجلس لمن له stats.view.
*/

/**
 * @return array<string, array{key: string, label: string, registered: int, completed: int, rate: int, pages: int|null, minutes: int|null}>
 */
function programStatsRows(): array
{
    return collect(ProgramStatsByType::cached() ?? [])->keyBy('key')->all();
}

beforeEach(function () {
    $this->courses = ProgramType::factory()->create(['slug' => 'training-courses', 'name' => 'الدورات التدريبية', 'sort' => 1]);
    $this->councils = ProgramType::factory()->create(['slug' => 'scientific-councils', 'name' => 'المجالس العلمية', 'sort' => 2]);
});

it('counts confirmed registrations and attendance per program type, without cancelled, waitlisted or deleted programs', function () {
    $course = Program::factory()->for($this->courses, 'type')->create();
    $otherCourse = Program::factory()->for($this->courses, 'type')->create();
    $deleted = Program::factory()->for($this->courses, 'type')->create();

    ProgramRegistration::factory()->for($course)->count(2)->create(['attended_at' => now()]);
    ProgramRegistration::factory()->for($otherCourse)->create();
    ProgramRegistration::factory()->for($course)->cancelled()->create(['attended_at' => now()]);
    ProgramRegistration::factory()->for($course)->waitlisted()->create();
    ProgramRegistration::factory()->for($deleted)->create(['attended_at' => now()]);
    $deleted->delete();

    $this->artisan('ejaz:refresh-stats')->assertSuccessful();
    $rows = programStatsRows();

    expect($rows['training-courses'])->toMatchArray(['label' => 'الدورات التدريبية', 'registered' => 3, 'completed' => 2, 'rate' => 67, 'pages' => null, 'minutes' => null])
        // نوع بلا برنامج له صفه بأصفار، ونسبته صفر بلا قسمة على صفر
        ->and($rows['scientific-councils'])->toMatchArray(['registered' => 0, 'completed' => 0, 'rate' => 0])
        ->and(array_keys($rows))->toBe(['training-courses', 'scientific-councils', CompetitionsSource::KEY]);
});

it('counts competition registrants once per person in published competitions, and submitters as completed', function () {
    $published = Competition::factory()->create();
    $second = Competition::factory()->create();
    $person = User::factory()->create();

    // المرسِل في مسابقتين يُعدّ مرة في الرقمين
    CompetitionSubmission::factory()->submitted()->for($published)->create(['user_id' => $person->id]);
    CompetitionSubmission::factory()->submitted()->for($second)->create(['user_id' => $person->id]);
    // مسجَّل في مسابقة الطلاب ولم يسلّم بعد
    CompetitionSubmission::factory()->for($published)->create(['registered_at' => now()]);
    // مسودة لم تُرسل، ومسابقة غير منشورة، وتسجيل ملغى (حذف ناعم): لا تُعدّ
    CompetitionSubmission::factory()->for($published)->create();
    CompetitionSubmission::factory()->submitted()->for(Competition::factory()->unpublished())->create();
    CompetitionSubmission::factory()->for($published)->create(['registered_at' => now()])->delete();

    $this->artisan('ejaz:refresh-stats')->assertSuccessful();

    expect(programStatsRows()['_competitions'])->toMatchArray([
        'label' => __('program_stats.types.competitions'), 'registered' => 2, 'completed' => 1, 'rate' => 50, 'pages' => null, 'minutes' => null,
    ]);
});

it('computes the completion rate safely', function () {
    expect(ProgramStatsByType::rate(0, 0))->toBe(0)
        ->and(ProgramStatsByType::rate(3, 1))->toBe(33)
        ->and(ProgramStatsByType::rate(2, 5))->toBe(100);
});

it('accepts a new source with one registration line, and lets it replace a row by its key', function () {
    app()->instance('test.reading-source', new class implements ProgramStatsSource
    {
        public function rows(): array
        {
            return [
                ['key' => 'reading', 'label' => 'قراءة كتاب', 'registered' => 4, 'completed' => 1, 'pages' => 120, 'minutes' => null],
                ['key' => 'scientific-councils', 'label' => 'المجالس العلمية', 'registered' => 5, 'completed' => 5, 'pages' => null, 'minutes' => 30],
            ];
        }
    });
    config()->set('program-stats.sources', [...config('program-stats.sources'), 'test.reading-source']);

    $rows = collect(ProgramStatsByType::compute())->keyBy('key');

    expect($rows->keys()->all())->toBe(['training-courses', 'scientific-councils', '_competitions', 'reading'])
        ->and($rows['reading'])->toMatchArray(['registered' => 4, 'completed' => 1, 'rate' => 25, 'pages' => 120, 'minutes' => null])
        ->and($rows['scientific-councils'])->toMatchArray(['registered' => 5, 'rate' => 100, 'minutes' => 30]);
});

it('refuses a registered source that does not implement the contract', function () {
    config()->set('program-stats.sources', [stdClass::class]);

    ProgramStatsByType::compute();
})->throws(InvalidArgumentException::class);

it('stores aggregates only, without personal data', function () {
    $user = User::factory()->create(['name' => 'مشارك سري', 'email' => 'secret-person@example.test']);
    ProgramRegistration::factory()->for(Program::factory()->for($this->courses, 'type'))->for($user)->create(['attended_at' => now()]);
    CompetitionSubmission::factory()->submitted()->for($user)->create();

    $this->artisan('ejaz:refresh-stats')->assertSuccessful();
    $stored = json_encode(ProgramStatsByType::cached(), JSON_UNESCAPED_UNICODE);

    expect($stored)->not->toContain('مشارك سري')->not->toContain('secret-person')
        ->and(collect(ProgramStatsByType::cached())->every(fn (array $row): bool => array_keys($row) === ['key', 'label', 'registered', 'completed', 'rate', 'pages', 'minutes']))->toBeTrue();
});

it('shows the comparison widget to stats.view holders from the cache alone', function () {
    ProgramRegistration::factory()->for(Program::factory()->for($this->courses, 'type'))->create(['attended_at' => now()]);
    $this->artisan('ejaz:refresh-stats');
    $this->actingAs(userWithRole(RoleName::BoardMember));

    DB::enableQueryLog();
    Livewire::test(ProgramStatsByTypeWidget::class)
        ->assertSee(__('program_stats.heading'))
        ->assertSee('الدورات التدريبية')
        ->assertSee('المجالس العلمية')
        ->assertSee(__('program_stats.types.competitions'))
        ->assertSee(__('program_stats.rate_value', ['rate' => 100]))
        // الوصف بعناصره لا نصاً مهرَّباً
        ->assertSeeHtml('data-program-stats-row="_competitions"')
        ->assertDontSeeHtml('&lt;span');

    $aggregates = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql): bool => str_contains($sql, 'program_registrations') || str_contains($sql, 'competition_submissions'));
    expect($aggregates)->toBeEmpty();
});

it('shows an empty widget before the first computation', function () {
    $this->actingAs(userWithRole(RoleName::BoardMember));

    Livewire::test(ProgramStatsByTypeWidget::class)->assertSee(__('program_stats.empty'));
});

it('hides the widget from those without stats.view', function () {
    foreach ([RoleName::ProgramManager, RoleName::ContentEditor, RoleName::Reviewer, RoleName::Secretary] as $role) {
        $this->actingAs(userWithRole($role));
        expect(ProgramStatsByTypeWidget::canView())->toBeFalse();
    }

    $this->actingAs(User::factory()->create());
    expect(ProgramStatsByTypeWidget::canView())->toBeFalse();

    $this->actingAs(userWithRole(RoleName::BoardMember));
    expect(ProgramStatsByTypeWidget::canView())->toBeTrue();
});

it('shows the program stats in the board portal to board members from the cache alone', function () {
    ProgramRegistration::factory()->for(Program::factory()->for($this->courses, 'type'))->count(2)->create();
    $this->artisan('ejaz:refresh-stats');

    $board = userWithRole(RoleName::BoardMember);
    AssemblyMembership::factory()->for($board)->create();

    DB::enableQueryLog();
    $this->actingAs($board)->get('/assembly')->assertOk()
        ->assertSee('data-board-program-stats', false)
        ->assertSee(__('program_stats.heading'))
        ->assertSee('data-program-stats-row="training-courses"', false)
        ->assertSee('data-program-stats-row="_competitions"', false);

    $aggregates = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql): bool => str_contains($sql, 'program_registrations') || str_contains($sql, 'competition_submissions'));
    expect($aggregates)->toBeEmpty();
});

it('keeps the program stats from assembly members without stats.view, guests, and before the first computation', function () {
    $member = AssemblyMembership::factory()->create()->user;
    $board = userWithRole(RoleName::BoardMember);
    AssemblyMembership::factory()->for($board)->create();

    // قبل أول حساب لا قسم ولا رقم مكان رقم
    $this->actingAs($board)->get('/assembly')->assertOk()->assertDontSee('data-board-program-stats', false);

    $this->artisan('ejaz:refresh-stats');

    $this->actingAs($member)->get('/assembly')->assertOk()
        ->assertSee(__('assembly.portal.records'))
        ->assertDontSee('data-board-program-stats', false);

    auth()->logout();
    $this->get('/assembly')->assertOk()->assertDontSee('data-board-program-stats', false);
});

it('counts a student who submitted an exam attempt as completed, though the registration stays a draft', function () {
    config(['competitions.exam.national_id_key' => 'test-hmac-key', 'competitions.student.min_questions' => 1]);
    $competition = Competition::factory()->student()->create(['slug' => 'students']);
    $round = $competition->rounds()->firstOrFail();
    studentQuestion($round);

    $submitted = registeredStudent($competition, 1);
    app(SubmitAttempt::class)->handle($submitted, app(StartAttempt::class)->handle($submitted, $round));
    // مسجل بدأ ولم يسلّم، ومسجل لم يبدأ
    app(StartAttempt::class)->handle(registeredStudent($competition, 2), $round);
    registeredStudent($competition, 3);

    $this->artisan('ejaz:refresh-stats')->assertSuccessful();

    expect(programStatsRows()['_competitions'])->toMatchArray(['registered' => 3, 'completed' => 1, 'rate' => 33]);
});

it('keeps refreshing the other portal numbers when a program stats source fails', function () {
    Exceptions::fake();
    config()->set('program-stats.sources', [stdClass::class]);

    $this->artisan('ejaz:refresh-stats')->assertSuccessful();

    Exceptions::assertReported(InvalidArgumentException::class);
    expect(HomePortalStats::cached())->not->toBeNull()
        ->and(ProgramStatsByType::cached())->toBeNull();
});

it('leaves the chart out of the admin dashboard for staff without stats.view', function () {
    $this->withoutVite();
    $this->artisan('ejaz:refresh-stats');

    $this->actingAs(userWithRole(RoleName::ProgramManager))->get('/admin')->assertOk()
        ->assertDontSee(__('program_stats.heading'));

    $this->actingAs(userWithRole(RoleName::BoardMember))->get('/admin')->assertOk()
        ->assertSee(__('program_stats.heading'));
});

it('counts a registration completed by its holder (completed_at, library programs from #126) as completed', function () {
    // العمود يأتي مع #126، فيُضاف هنا داخل معاملة الاختبار إن لم يوجد بعد
    if (! Schema::hasColumn('program_registrations', 'completed_at')) {
        Schema::table('program_registrations', fn (Blueprint $table) => $table->timestamp('completed_at')->nullable());
    }

    $program = Program::factory()->for($this->courses, 'type')->create();
    ProgramRegistration::factory()->for($program)->create();
    $done = ProgramRegistration::factory()->for($program)->create();
    DB::table('program_registrations')->where('id', $done->id)->update(['completed_at' => now()]);

    expect(collect((new ProgramTypesSource)->rows())->keyBy('key')['training-courses'])->toMatchArray(['registered' => 2, 'completed' => 1]);
});
