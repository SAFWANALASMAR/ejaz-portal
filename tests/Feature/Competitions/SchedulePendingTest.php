<?php

use App\Actions\Competitions\RegisterStudent;
use App\Actions\Competitions\SaveCompetition;
use App\Enums\CompetitionStatus;
use App\Enums\RoleName;
use App\Filament\Resources\Competitions\Pages\EditCompetition;
use App\Models\Competition;
use App\Models\CompetitionRound;
use App\Models\User;
use App\Support\Dates;
use App\Support\HomeShowcase;
use App\Support\SchoolNetworkLimits;
use Database\Seeders\BrahinCompetitionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/*
| «المواعيد تُعلن لاحقاً» (قرار صفوان، 12 نوفمبر 2026 — docs/decisions/2026-11-12-feature-competition-schedule-pending.md): تُنشر
| صفحة المسابقة قبل اعتماد مواعيدها وإدخال أسئلتها، بلا موعدي التسجيل ولا يوم المرحلة وتاريخها وحالتها، والتسجيل مغلق.
*/

beforeEach(function () {
    config(['competitions.exam.national_id_key' => 'test-hmac-key', 'competitions.student.min_questions' => 1]);

    (new BrahinCompetitionSeeder)->run();
    $this->competition = Competition::query()->where('slug', 'brahin-1448')->sole();
    // موعدا التسجيل المخزنان مفتوحان الآن، فما يُغلق في الاختبارات يغلقه الخيار وحده
    $this->competition->forceFill(['is_published' => true, 'schedule_pending' => true, 'opens_at' => now()->subDay(), 'closes_at' => now()->addWeek()])->save();
    $this->competition = $this->competition->fresh();
    SchoolNetworkLimits::forget();
});

/**
 * بيانات نموذج اللوحة للمسابقة المبذورة، وما يُغيَّر منها.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function schedulePendingForm(Competition $competition, array $overrides = []): array
{
    return [...$competition->only(['title', 'slug', 'opens_at', 'closes_at']), 'student_mode' => true, 'is_published' => true, ...$overrides];
}

it('is off by default', function () {
    expect(Competition::factory()->create()->fresh()->schedule_pending)->toBeFalse();
});

it('keeps a published competition upcoming and closed while its dates are stored as open', function () {
    expect($this->competition->status())->toBe(CompetitionStatus::Upcoming)
        ->and($this->competition->isOpen())->toBeFalse();

    expect(fn () => app(RegisterStudent::class)->handle(User::factory()->create(), $this->competition, []))
        ->toThrow(AuthorizationException::class, __('competitions.student.registration_closed'));
});

it('shows the page without the registration dates, the stage days or their state, and without a register link', function () {
    /** @var CompetitionRound $round */
    $round = $this->competition->rounds()->firstOrFail();
    $round->forceFill(['starts_at' => now()->subHour(), 'ends_at' => now()->addHour()])->save();
    $this->competition->forceFill(['show_round_dates' => true])->save();

    $html = (string) $this->get('/competitions/brahin-1448')->assertOk()->getContent();

    expect($html)->toContain('data-schedule-pending')->toContain(__('competitions.show.schedule_pending'))
        ->not->toContain('data-competition-dates')
        ->not->toContain(__('competitions.student.registration_opens'))
        ->not->toContain('data-round-day')->not->toContain('data-round-dates')
        ->not->toContain(__('competitions.student.running'))
        ->not->toContain(route('competitions.student.register', 'brahin-1448'))
        // الفعالية بلا تاريخ بدء لا تُكتب في البيانات المنظمة
        ->not->toContain('"@type":"Event"')
        ->and(substr_count($html, 'data-round-pending'))->toBe($this->competition->rounds()->count());
});

it('shows the dates and the stage days again once the option is off', function () {
    $this->competition->forceFill(['schedule_pending' => false])->save();

    $html = (string) $this->get('/competitions/brahin-1448')->assertOk()->getContent();

    expect($html)->toContain('data-competition-dates')->toContain('data-round-day')->toContain('"@type":"Event"')
        ->toContain(route('competitions.student.register', 'brahin-1448'))
        ->not->toContain('data-schedule-pending')->not->toContain('data-round-pending');
});

it('lists it as upcoming without a date, never as the current one or in the archive', function () {
    $this->get('/competitions')->assertOk()
        ->assertSee('data-competitions-upcoming', false)
        ->assertDontSee('data-competitions-current', false)
        ->assertDontSee('data-competitions-past', false)
        ->assertSee(__('competitions.list.schedule_pending'))
        ->assertDontSee(__('competitions.list.opens', ['date' => Dates::display($this->competition->opens_at)]));

    // وموعداها المخزنان في الماضي لا ينقلانها إلى الأرشيف
    $this->competition->forceFill(['opens_at' => now()->subWeek(), 'closes_at' => now()->subDay()])->save();

    $this->get('/competitions')->assertOk()
        ->assertSee('data-competitions-upcoming', false)
        ->assertDontSee('data-competitions-past', false);
});

it('gives the home slide no date and the school networks no wider limits', function () {
    /** @var CompetitionRound $round */
    $round = $this->competition->rounds()->firstOrFail();
    $round->forceFill(['starts_at' => now()->subHour(), 'ends_at' => now()->addHour()])->save();
    SchoolNetworkLimits::forget();

    $prize = HomeShowcase::prize();

    expect($prize)->not->toBeNull()
        ->and($prize['status'])->toBe(CompetitionStatus::Upcoming)
        ->and($prize['date'])->toBeNull()
        ->and(SchoolNetworkLimits::registrationOpen())->toBeFalse()
        ->and(SchoolNetworkLimits::examWindow())->toBeFalse();

    $this->competition->forceFill(['schedule_pending' => false])->save();
    SchoolNetworkLimits::forget();

    expect(HomeShowcase::prize()['date'])->not->toBeNull()
        ->and(SchoolNetworkLimits::registrationOpen())->toBeTrue()
        ->and(SchoolNetworkLimits::examWindow())->toBeTrue();
});

it('publishes from the action before the questions arrive, and refuses to announce the dates without them', function () {
    $director = userWithRole(RoleName::ExecutiveDirector);
    $this->competition->forceFill(['is_published' => false, 'schedule_pending' => false])->save();
    $draft = $this->competition->fresh();

    expect($draft->roundQuestions()->exists())->toBeFalse();

    // بلا الخيار: القاعدة القائمة «لا تُنشر مسابقة بلا أسئلة»
    expect(fn () => app(SaveCompetition::class)->handle($director, $draft, schedulePendingForm($draft)))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey('is_published'));

    app(SaveCompetition::class)->handle($director, $draft, schedulePendingForm($draft, ['schedule_pending' => true]));
    $published = $this->competition->fresh();

    expect($published->is_published)->toBeTrue()
        ->and($published->schedule_pending)->toBeTrue()
        ->and($published->status())->toBe(CompetitionStatus::Upcoming);

    // وإطفاؤه في منشورة بلا أسئلة مرفوض، فلا يُفتح تسجيل بلا بنك
    expect(fn () => app(SaveCompetition::class)->handle($director, $published, schedulePendingForm($published, ['schedule_pending' => false])))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey('is_published'));

    expect($this->competition->fresh()->schedule_pending)->toBeTrue();
});

it('rejects a non-boolean value, and refuses to turn the option on after the first registration', function () {
    $director = userWithRole(RoleName::ExecutiveDirector);

    expect(fn () => app(SaveCompetition::class)->handle($director, $this->competition, schedulePendingForm($this->competition, ['schedule_pending' => 'yes'])))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey('schedule_pending'));

    $this->competition->forceFill(['schedule_pending' => false])->save();
    $open = $this->competition->fresh();
    registeredStudent($open);

    expect(fn () => app(SaveCompetition::class)->handle($director, $open, schedulePendingForm($open, ['schedule_pending' => true])))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey('schedule_pending'));

    expect($this->competition->fresh()->schedule_pending)->toBeFalse();
});

it('offers the option in the panel form', function () {
    $this->actingAs(userWithRole(RoleName::ExecutiveDirector));

    Livewire::test(EditCompetition::class, ['record' => $this->competition->getRouteKey()])
        ->assertFormFieldExists('schedule_pending')
        ->assertFormSet(['schedule_pending' => true]);
});
