<?php

use App\Actions\Registers\SaveAnnualPlanItem;
use App\Enums\RoleName;
use App\Models\AnnualPlanItem;
use App\Models\Program;
use App\Models\ProgramRegistration;
use App\Support\AnnualPlanProgress;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

/*
| الخطة السنوية للبرامج (requirements/13 §3): كل بند يُربط بالبرنامج المنفذ، فيُحسب المنفَّذ والمستفيدون
| الفعليون آلياً، وتتغير نسبة الإنجاز لكل إدارة ونوع بتنفيذ البرنامج المربوط.
*/

beforeEach(function () {
    $this->director = userWithRole(RoleName::ExecutiveDirector);
    $this->projects = userWithRole(RoleName::ProjectsManager);
});

function planData(array $overrides = []): array
{
    return [
        'year' => 2026,
        'department' => 'إدارة تجريبية',
        'type' => 'نوع تجريبي',
        'title' => 'برنامج تجريبي',
        'objective_breakdown' => null,
        'estimated_budget' => '1500.50',
        'supporting_entities' => null,
        'target_group' => 'فئة تجريبية',
        'target_beneficiaries' => 100,
        'preparation_period' => null,
        'starts_on' => '2026-03-01',
        'ends_on' => '2026-03-05',
        'approvals' => null,
        'program_id' => null,
        'notes' => null,
        ...$overrides,
    ];
}

it('lets the executive director and the projects manager manage, and the board read', function () {
    $item = app(SaveAnnualPlanItem::class)->handle($this->projects, new AnnualPlanItem, planData());
    app(SaveAnnualPlanItem::class)->handle($this->director, $item, planData(['title' => 'برنامج معدّل']));

    expect($item->fresh()->title)->toBe('برنامج معدّل')
        ->and(Activity::where('subject_type', AnnualPlanItem::class)->where('event', 'updated')->exists())->toBeTrue();

    $board = userWithRole(RoleName::BoardMember);
    expect($board->can('view', $item))->toBeTrue()
        ->and(fn () => app(SaveAnnualPlanItem::class)->handle($board, new AnnualPlanItem, planData()))->toThrow(AuthorizationException::class);

    foreach ([RoleName::Secretary, RoleName::ProgramManager, RoleName::ContentEditor, RoleName::Member] as $role) {
        expect(userWithRole($role)->can('viewAny', AnnualPlanItem::class))->toBeFalse();
    }
});

it('validates the plan item', function (array $override, string $field) {
    expect(fn () => app(SaveAnnualPlanItem::class)->handle($this->director, new AnnualPlanItem, planData($override)))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey($field));
})->with([
    'no title' => [['title' => ''], 'title'],
    'bad year' => [['year' => 1990], 'year'],
    'end before start' => [['starts_on' => '2026-03-05', 'ends_on' => '2026-03-01'], 'ends_on'],
    'negative budget' => [['estimated_budget' => -1], 'estimated_budget'],
    'missing program' => [['program_id' => 999999], 'program_id'],
]);

it('links a program to one plan item only', function () {
    $program = Program::factory()->create();
    app(SaveAnnualPlanItem::class)->handle($this->director, new AnnualPlanItem, planData(['program_id' => $program->id]));

    expect(fn () => app(SaveAnnualPlanItem::class)->handle($this->director, new AnnualPlanItem, planData(['program_id' => $program->id])))->toThrow(ValidationException::class);
});

it('computes executed and actual beneficiaries from the linked program', function () {
    $item = AnnualPlanItem::factory()->create();
    expect($item->isExecuted())->toBeFalse()->and($item->actualBeneficiaries())->toBe(0);

    $program = Program::factory()->create();
    $item->update(['program_id' => $program->id]);
    ProgramRegistration::factory()->count(3)->create(['program_id' => $program->id]);
    ProgramRegistration::factory()->cancelled()->create(['program_id' => $program->id]);

    // لم يُنفَّذ بعد: البرنامج لم ينته
    expect($item->fresh()->isExecuted())->toBeFalse()
        ->and($item->fresh()->actualBeneficiaries())->toBe(3);

    $program->update(['starts_at' => now()->subDays(3), 'ends_at' => now()->subDays(2)]);
    expect($item->fresh()->isExecuted())->toBeTrue();

    // متى سُجّل الحضور فالحاضرون هم المستفيدون الفعليون
    $program->registrations()->where('status', 'confirmed')->first()->update(['attended_at' => now()->subDays(2)]);
    expect($item->fresh()->actualBeneficiaries())->toBe(1);

    // والبرنامج المحذوف لا يُعد منفذاً
    $program->delete();
    expect($item->fresh()->isExecuted())->toBeFalse();
});

it('changes the achievement rate per department and type as the linked program is executed', function () {
    $program = Program::factory()->create();
    $linked = AnnualPlanItem::factory()->create(['department' => 'إدارة أ', 'type' => 'نوع أ', 'target_beneficiaries' => 10, 'program_id' => $program->id]);
    AnnualPlanItem::factory()->create(['department' => 'إدارة أ', 'type' => 'نوع ب', 'target_beneficiaries' => 30]);
    AnnualPlanItem::factory()->create(['department' => 'إدارة ب', 'type' => 'نوع أ', 'target_beneficiaries' => 20]);
    AnnualPlanItem::factory()->create(['year' => 2025, 'department' => 'إدارة أ']);
    ProgramRegistration::factory()->count(4)->create(['program_id' => $program->id, 'attended_at' => now()]);

    $before = AnnualPlanProgress::for(2026);
    expect($before['overall'])->toMatchArray(['items' => 3, 'executed' => 0, 'percent' => 0, 'target' => 60, 'actual' => 0])
        ->and($before['departments']['إدارة أ']['items'])->toBe(2);

    $program->update(['starts_at' => now()->subDays(3), 'ends_at' => now()->subDays(2)]);

    $after = AnnualPlanProgress::for(2026);
    expect($after['overall'])->toMatchArray(['items' => 3, 'executed' => 1, 'percent' => 33, 'target' => 60, 'actual' => 4])
        ->and($after['departments']['إدارة أ'])->toMatchArray(['items' => 2, 'executed' => 1, 'percent' => 50, 'actual' => 4])
        ->and($after['departments']['إدارة ب'])->toMatchArray(['items' => 1, 'executed' => 0, 'percent' => 0])
        ->and($after['types']['نوع أ'])->toMatchArray(['items' => 2, 'executed' => 1, 'percent' => 50, 'target' => 30, 'actual' => 4])
        ->and($linked->fresh()->isExecuted())->toBeTrue();

    expect(AnnualPlanProgress::for(2030)['overall'])->toMatchArray(['items' => 0, 'percent' => 0]);
});

it('refuses a program still held by a deleted plan item with a validation error', function () {
    $program = Program::factory()->create();
    app(SaveAnnualPlanItem::class)->handle($this->director, new AnnualPlanItem, planData(['program_id' => $program->id]))->delete();

    expect(fn () => app(SaveAnnualPlanItem::class)->handle($this->director, new AnnualPlanItem, planData(['program_id' => $program->id])))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey('program_id'));
});
