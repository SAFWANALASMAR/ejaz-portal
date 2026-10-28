<?php

use App\Actions\Assistant\SaveAssistantSettings;
use App\Actions\Stats\RequestStatsAnalysis;
use App\Contracts\AiAssistant;
use App\Enums\ActivityEvent;
use App\Enums\RoleName;
use App\Enums\SaudiRegion;
use App\Enums\StatsAnalysisStatus;
use App\Filament\Pages\StatsAnalysisPage;
use App\Filament\Widgets\StatsAnalysisWidget;
use App\Jobs\GenerateStatsAnalysis;
use App\Models\AssistantUsage;
use App\Models\Competition;
use App\Models\CompetitionSubmission;
use App\Models\Program;
use App\Models\ProgramRegistration;
use App\Models\ProgramType;
use App\Models\StatsAnalysis;
use App\Models\User;
use App\Services\Assistant\AssistantReply;
use App\Services\Assistant\FakeAiAssistant;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/*
| تحليل الإحصائيات بالذكاء الاصطناعي (إضافة صفوان، 19 أكتوبر 2026): لمن له stats.view، بمفتاح مستقل وبالمزود والسقف
| نفسيهما للمساعد، وأرقام مجمّعة وحدها إلى المزود، والأرقام المعروضة من الخادم.
*/

function enableAnalysis(array $attributes = []): FakeAiAssistant
{
    return enableAssistant(['analysis_enabled' => true, 'is_enabled' => false, 'generation_enabled' => false, ...$attributes]);
}

function lastMonth(): array
{
    $today = now((string) config('app.display_timezone'));

    return ['from' => $today->copy()->subDays(29)->toDateString(), 'to' => $today->toDateString()];
}

/**
 * نشاط في الفترة بأصحاب بيانات شخصية معروفة، ليُتحقق أنها لا تخرج.
 */
function seedActivity(): void
{
    $type = ProgramType::factory()->create(['name' => 'دورة تدريبية']);
    $program = Program::factory()->create(['title' => 'دورة الإعجاز في الفلك', 'program_type_id' => $type->id]);
    $people = collect(['سارة المطيري' => '0551112233', 'خالد العتيبي' => '0554445566', 'نورة القحطاني' => '0557778899'])
        ->map(fn (string $phone, string $name) => User::factory()->create(['name' => $name, 'email' => str_replace(' ', '.', $phone).'@person.test', 'phone' => '+966'.substr($phone, 1)]));

    [$first, $second, $third] = $people->values()->all();
    ProgramRegistration::factory()->create(['program_id' => $program->id, 'user_id' => $first->id, 'attended_at' => now()]);
    ProgramRegistration::factory()->waitlisted()->create(['program_id' => $program->id, 'user_id' => $second->id]);
    ProgramRegistration::factory()->cancelled()->create(['program_id' => $program->id, 'user_id' => $third->id]);

    $competition = Competition::factory()->create(['title' => 'مسابقة التدبر']);
    CompetitionSubmission::factory()->submitted()->create(['competition_id' => $competition->id, 'user_id' => $first->id, 'region' => SaudiRegion::Riyadh]);
    CompetitionSubmission::factory()->submitted()->create(['competition_id' => $competition->id, 'user_id' => $second->id, 'region' => SaudiRegion::Riyadh]);
    CompetitionSubmission::factory()->create(['competition_id' => $competition->id, 'user_id' => $third->id]);
}

it('opens the analysis only to those who see the statistics, and only when enabled', function () {
    $this->actingAs(userWithRole(RoleName::BoardMember))->get(StatsAnalysisPage::getUrl())->assertForbidden();

    enableAnalysis();

    $this->actingAs(userWithRole(RoleName::BoardMember))->get(StatsAnalysisPage::getUrl())->assertOk()->assertSee(__('analysis.analyze'));
    $this->actingAs(userWithRole(RoleName::ExecutiveDirector))->get(StatsAnalysisPage::getUrl())->assertOk();
    $this->actingAs(userWithRole(RoleName::ContentEditor))->get(StatsAnalysisPage::getUrl())->assertForbidden();
    $this->actingAs(userWithRole(RoleName::Secretary))->get(StatsAnalysisPage::getUrl())->assertForbidden();

    expect(fn () => app(RequestStatsAnalysis::class)->handle(userWithRole(RoleName::ContentEditor), lastMonth()))->toThrow(AuthorizationException::class);
});

it('shows the «analyze» button in the statistics dashboard', function () {
    enableAnalysis();
    $this->actingAs(userWithRole(RoleName::BoardMember));

    expect(StatsAnalysisWidget::canView())->toBeTrue();
    Livewire::test(StatsAnalysisWidget::class)->assertSee(__('analysis.analyze'))->assertSee(StatsAnalysisPage::getUrl());
});

it('sends only aggregated figures to the provider, never personal data', function () {
    $fake = enableAnalysis();
    seedActivity();

    $analysis = app(RequestStatsAnalysis::class)->handle(userWithRole(RoleName::BoardMember, ['name' => 'عضو المجلس', 'email' => 'board@person.test']), lastMonth());

    expect($fake->requests)->toHaveCount(1);
    ['system' => $system, 'message' => $message] = $fake->requests[0];

    expect($system)->toBe(GenerateStatsAnalysis::SYSTEM)
        ->and($message)->toStartWith('<figures>')->toEndWith('</figures>')
        ->and($message)->toContain('دورة الإعجاز في الفلك')->toContain('مسابقة التدبر')->toContain('دورة تدريبية');

    foreach (['سارة', 'خالد', 'نورة', 'المطيري', '@person.test', '055', '+966', 'عضو المجلس', 'board@'] as $personal) {
        expect($message)->not->toContain($personal);
    }

    expect(json_encode($analysis->fresh()->figures, JSON_UNESCAPED_UNICODE))->not->toContain('سارة')->not->toContain('@person.test');
});

it('builds the figures on the server and stores the report beside them', function () {
    $fake = enableAnalysis();
    seedActivity();
    $fake->reply = "البرامج\nنشاط جيد 999.";

    $analysis = app(RequestStatsAnalysis::class)->handle(userWithRole(RoleName::BoardMember), lastMonth())->fresh();
    $figures = collect($analysis->figures)->map(fn (array $rows): array => collect($rows)->mapWithKeys(fn (array $row, string $key): array => [$key => $row['value']])->all())->all();

    expect($analysis->status)->toBe(StatsAnalysisStatus::Done)
        ->and($analysis->report)->toBe("البرامج\nنشاط جيد 999.")
        ->and($figures['programs'])->toMatchArray(['registrations' => 3, 'confirmed' => 1, 'waitlisted' => 1, 'cancelled' => 1, 'attended' => 1])
        ->and($figures['competitions'])->toMatchArray(['submitted' => 2, 'drafts' => 1])
        ->and($analysis->figures['regions']['region_1'])->toBe(['label' => SaudiRegion::Riyadh->getLabel(), 'value' => 2])
        ->and($analysis->figures['top_programs']['program_1'])->toBe(['label' => 'دورة الإعجاز في الفلك', 'value' => 1]);

    $this->actingAs(userWithRole(RoleName::BoardMember))->get(StatsAnalysisPage::getUrl())
        ->assertSee('دورة الإعجاز في الفلك')
        ->assertSee(__('analysis.figures.attended'))
        ->assertSee(__('analysis.disclaimer'));
});

it('counts the analysis cost on the shared monthly cap', function () {
    $fake = enableAnalysis(['input_price' => 2, 'output_price' => 10]);

    $analysis = app(RequestStatsAnalysis::class)->handle(userWithRole(RoleName::BoardMember), lastMonth())->fresh();

    expect((float) $analysis->cost)->toBeGreaterThan(0)
        ->and(AssistantUsage::monthCost())->toBe((float) $analysis->cost);
});

it('refuses when disabled, without a provider, or at the cap', function (array $settings) {
    $fake = enableAnalysis($settings);
    AssistantUsage::add(5);

    expect(fn () => app(RequestStatsAnalysis::class)->handle(userWithRole(RoleName::BoardMember), lastMonth()))->toThrow(ValidationException::class)
        ->and(StatsAnalysis::query()->count())->toBe(0)
        ->and($fake->requests)->toBe([]);
})->with([
    'disabled' => [['analysis_enabled' => false]],
    'no provider' => [['provider' => null]],
    'monthly cap reached' => [['monthly_cap' => 5]],
]);

it('marks the report as failed when the provider fails, without cost', function () {
    $fake = enableAnalysis();
    $fake->fail = true;

    $analysis = app(RequestStatsAnalysis::class)->handle(userWithRole(RoleName::BoardMember), lastMonth())->fresh();

    expect($analysis->status)->toBe(StatsAnalysisStatus::Failed)
        ->and($analysis->report)->toBeNull()
        ->and(AssistantUsage::monthCost())->toBe(0.0);
});

it('counts an estimated cost on the cap when the analysis times out', function () {
    $fake = enableAnalysis();
    $fake->timeout = true;

    $analysis = app(RequestStatsAnalysis::class)->handle(userWithRole(RoleName::BoardMember), lastMonth())->fresh();

    expect($analysis->status)->toBe(StatsAnalysisStatus::Failed)
        ->and($analysis->output_tokens)->toBe((int) config('assistant.max_output_tokens'))
        ->and((float) $analysis->cost)->toBeGreaterThan(0.0)
        ->and(AssistantUsage::monthCost())->toBe((float) $analysis->cost);
});

it('validates the period', function (array $period) {
    enableAnalysis();

    expect(fn () => app(RequestStatsAnalysis::class)->handle(userWithRole(RoleName::BoardMember), $period))->toThrow(ValidationException::class);
})->with([
    'reversed' => [['from' => '2026-10-10', 'to' => '2026-10-01']],
    'future' => [['from' => '2026-10-01', 'to' => now()->addDays(3)->toDateString()]],
    'too long' => [['from' => now()->subDays(400)->toDateString(), 'to' => now()->toDateString()]],
    'not a date' => [['from' => 'x', 'to' => 'y']],
]);

it('limits the analyses each account asks for in an hour', function () {
    enableAnalysis();
    $member = userWithRole(RoleName::BoardMember);

    foreach (range(1, RequestStatsAnalysis::PER_HOUR) as $i) {
        app(RequestStatsAnalysis::class)->handle($member, lastMonth());
    }

    expect(fn () => app(RequestStatsAnalysis::class)->handle($member, lastMonth()))->toThrow(ValidationException::class);
});

it('runs the analysis from the page action', function () {
    enableAnalysis();
    $this->actingAs(userWithRole(RoleName::BoardMember));

    Livewire::test(StatsAnalysisPage::class)
        ->callAction('analyze', lastMonth())
        ->assertNotified(__('analysis.queued'));

    expect(StatsAnalysis::query()->sole()->status)->toBe(StatsAnalysisStatus::Done);
});

it('records each analysis request in the activity log', function () {
    enableAnalysis();
    $member = userWithRole(RoleName::BoardMember);

    $analysis = app(RequestStatsAnalysis::class)->handle($member, lastMonth());
    $activity = Activity::query()->where('event', ActivityEvent::StatsAnalysisRequested->value)->sole();

    expect($activity->causer_id)->toBe($member->id)
        ->and($activity->subject_id)->toBe($analysis->id)
        ->and($activity->properties->all())->toBe(lastMonth());
});

it('stops polling for a report stuck in the queue, and a scheduled command, not the page, marks it as failed', function () {
    enableAnalysis();
    $member = userWithRole(RoleName::BoardMember);
    $stuck = StatsAnalysis::query()->create(['requested_by' => $member->id, 'period_from' => now(), 'period_to' => now(), 'status' => StatsAnalysisStatus::Queued, 'figures' => []]);
    $stuck->forceFill(['created_at' => now()->subMinutes(GenerateStatsAnalysis::STALE_MINUTES + 1)])->save();

    // مراجعة ما بعد v0.12.0 (مهمة ٢): العرض والاستطلاع لا يكتبان في القاعدة
    $this->actingAs($member)->get(StatsAnalysisPage::getUrl())->assertOk()->assertDontSee('wire:poll', false);
    expect($stuck->fresh()->status)->toBe(StatsAnalysisStatus::Queued);

    $fresh = StatsAnalysis::query()->create(['requested_by' => $member->id, 'period_from' => now(), 'period_to' => now(), 'status' => StatsAnalysisStatus::Queued, 'figures' => []]);
    $this->get(StatsAnalysisPage::getUrl())->assertSee('wire:poll', false);

    $this->artisan('ejaz:fail-stale-analyses')->expectsOutput('1')->assertSuccessful();
    expect($stuck->fresh()->status)->toBe(StatsAnalysisStatus::Failed)
        ->and($fresh->fresh()->status)->toBe(StatsAnalysisStatus::Queued);
    $fresh->delete();

    // وما يرمي من خطأ غير تعذّر المزود
    $other = StatsAnalysis::query()->create(['requested_by' => $member->id, 'period_from' => now(), 'period_to' => now(), 'status' => StatsAnalysisStatus::Queued, 'figures' => []]);
    (new GenerateStatsAnalysis($other->id))->failed(new RuntimeException('boom'));
    expect($other->fresh()->status)->toBe(StatsAnalysisStatus::Failed);
});

it('does not write its report over a failure the scheduled command marked while the provider was answering', function () {
    enableAnalysis();
    $member = userWithRole(RoleName::BoardMember);
    $analysis = StatsAnalysis::query()->create(['requested_by' => $member->id, 'period_from' => now(), 'period_to' => now(), 'status' => StatsAnalysisStatus::Queued, 'figures' => []]);

    // المزود يجيب بعد أن علّم الأمر المجدول التقرير «تعذّر» (مراجعة ما بعد v0.12.0، مهمة ٢)
    app()->instance(AiAssistant::class, new class($analysis->id) implements AiAssistant
    {
        public function __construct(private int $id) {}

        public function answer(string $system, string $message): AssistantReply
        {
            StatsAnalysis::query()->whereKey($this->id)->update(['status' => StatsAnalysisStatus::Failed->value]);

            return new AssistantReply(__('analysis.fake_report'), 10, 10);
        }
    });

    app()->call([new GenerateStatsAnalysis($analysis->id), 'handle']);

    expect($analysis->fresh()->status)->toBe(StatsAnalysisStatus::Failed)
        ->and($analysis->fresh()->report)->toBeNull();
});

it('names only published programs and competitions', function () {
    $fake = enableAnalysis();
    $hidden = Program::factory()->create(['title' => 'برنامج غير منشور', 'is_published' => false]);
    ProgramRegistration::factory()->create(['program_id' => $hidden->id]);

    app(RequestStatsAnalysis::class)->handle(userWithRole(RoleName::BoardMember), lastMonth());

    expect($fake->requests[0]['message'])->not->toContain('برنامج غير منشور');
});

it('does not enable the analysis without a provider, a cap and prices', function () {
    expect(fn () => app(SaveAssistantSettings::class)->handle(userWithRole(RoleName::TechnicalAdmin), ['analysis_enabled' => true]))
        ->toThrow(ValidationException::class);
});
