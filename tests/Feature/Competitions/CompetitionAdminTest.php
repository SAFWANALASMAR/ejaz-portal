<?php

use App\Actions\Competitions\SaveCompetition;
use App\Enums\CompetitionStatus;
use App\Enums\QuestionType;
use App\Enums\RoleName;
use App\Filament\Resources\Competitions\CompetitionResource;
use App\Filament\Resources\Competitions\Pages\CreateCompetition;
use App\Filament\Resources\Competitions\Pages\EditCompetition;
use App\Models\Competition;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->manager = userWithRole(RoleName::ProgramManager);
});

/**
 * مسابقة كاملة بشكل نموذج اللوحة، وما يُغيَّر منها.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function competitionForm(array $overrides = []): array
{
    return [
        'title' => 'مسابقة تجريبية',
        'slug' => 'demo-competition',
        'categories' => ['فئة أ', 'فئة ب'],
        'opens_at' => now()->addDay()->format('Y-m-d H:i:s'),
        'closes_at' => now()->addDays(10)->format('Y-m-d H:i:s'),
        'is_published' => true,
        'criteria' => [
            ['name' => 'المعيار الأول', 'weight' => 70, 'max_score' => 10],
            ['name' => 'المعيار الثاني', 'weight' => 30, 'max_score' => 5],
        ],
        'questions' => [
            ['type' => 'text', 'prompt' => 'سؤال نصي', 'is_required' => true],
            ['type' => 'multiple_choice', 'prompt' => 'سؤال اختيار', 'is_required' => true, 'points' => 3, 'options' => [
                ['label' => 'خيار أول', 'is_correct' => false],
                ['label' => 'خيار ثانٍ', 'is_correct' => true],
            ]],
            ['type' => 'video', 'prompt' => 'سؤال فيديو', 'is_required' => false, 'max_video_seconds' => 180, 'max_video_mb' => 200],
        ],
        ...$overrides,
    ];
}

it('opens the competitions screen to the program manager, lets the board look only, and keeps others out', function () {
    $competition = Competition::factory()->create();

    $this->actingAs($this->manager)->get(CompetitionResource::getUrl('index'))->assertOk();
    $this->actingAs($this->manager)->get(CompetitionResource::getUrl('create'))->assertOk();
    $this->actingAs($this->manager)->get(CompetitionResource::getUrl('edit', ['record' => $competition]))->assertOk();

    $board = userWithRole(RoleName::BoardMember);
    $this->actingAs($board)->get(CompetitionResource::getUrl('index'))->assertOk();
    $this->actingAs($board)->get(CompetitionResource::getUrl('create'))->assertForbidden();
    $this->actingAs($board)->get(CompetitionResource::getUrl('edit', ['record' => $competition]))->assertForbidden();

    foreach ([RoleName::ContentEditor, RoleName::Secretary, RoleName::Reviewer] as $role) {
        $this->actingAs(userWithRole($role))->get(CompetitionResource::getUrl('index'))->assertForbidden();
    }

    $this->actingAs(userWithRole(RoleName::Member))->get(CompetitionResource::getUrl('index'))->assertForbidden();
});

it('creates a competition with its criteria and question bank, and logs it', function () {
    $this->actingAs($this->manager);

    Livewire::test(CreateCompetition::class)
        ->fillForm(competitionForm())
        ->call('create')
        ->assertHasNoFormErrors();

    $competition = Competition::where('slug', 'demo-competition')->sole();
    $choice = $competition->questions[1];

    expect($competition->uuid)->not->toBeEmpty()
        ->and($competition->categoryList())->toBe(['فئة أ', 'فئة ب'])
        ->and($competition->criteria->pluck('weight')->all())->toBe([70, 30])
        ->and($competition->questions->pluck('type')->all())->toBe([QuestionType::Text, QuestionType::MultipleChoice, QuestionType::Video])
        ->and($choice->optionList())->toBe([['key' => 'a', 'label' => 'خيار أول'], ['key' => 'b', 'label' => 'خيار ثانٍ']])
        ->and($choice->correct_option)->toBe('b')
        ->and($choice->points)->toBe(3)
        ->and($competition->questions[2]->max_video_mb)->toBe(200)
        ->and($competition->status())->toBe(CompetitionStatus::Upcoming)
        ->and(Activity::whereMorphedTo('subject', $competition)->where('event', 'created')->exists())->toBeTrue();
});

it('edits the question bank: updates, reorders, adds and removes questions', function () {
    $competition = Competition::factory()->withQuestions()->create();
    [$text, $choice, $video] = $competition->questions->all();
    $this->actingAs($this->manager);

    $page = Livewire::test(EditCompetition::class, ['record' => $competition->getRouteKey()]);

    expect(array_column($page->get('data.questions'), 'prompt'))->toBe(['سؤال نصي تجريبي', 'سؤال اختيار تجريبي', 'سؤال فيديو تجريبي']);

    $page->fillForm(['questions' => [
        ['id' => $choice->id, 'type' => 'multiple_choice', 'prompt' => 'سؤال اختيار معدّل', 'is_required' => true, 'points' => 5, 'options' => [
            ['label' => 'أ', 'is_correct' => true], ['label' => 'ب', 'is_correct' => false], ['label' => 'ج', 'is_correct' => false],
        ]],
        ['id' => $text->id, 'type' => 'text', 'prompt' => 'سؤال نصي', 'is_required' => false],
        ['type' => 'text', 'prompt' => 'سؤال جديد', 'is_required' => true],
    ]])
        ->call('save')
        ->assertHasNoFormErrors();

    $questions = $competition->refresh()->questions;

    expect($questions->pluck('prompt')->all())->toBe(['سؤال اختيار معدّل', 'سؤال نصي', 'سؤال جديد'])
        ->and($questions[0]->id)->toBe($choice->id)
        ->and($questions[0]->correct_option)->toBe('a')
        ->and($questions[0]->points)->toBe(5)
        ->and($questions[1]->is_required)->toBeFalse()
        ->and($competition->questions()->whereKey($video->id)->exists())->toBeFalse();
});

it('refuses bad criteria and questions from the action, whoever calls it', function (array $overrides, string $field) {
    expect(fn () => app(SaveCompetition::class)->handle($this->manager, new Competition, competitionForm($overrides)))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey($field));

    expect(Competition::count())->toBe(0);
})->with([
    'weights not 100' => [['criteria' => [['name' => 'م', 'weight' => 50, 'max_score' => 10]]], 'criteria'],
    'two correct options' => [['questions' => [['type' => 'multiple_choice', 'prompt' => 'س', 'options' => [['label' => 'أ', 'is_correct' => true], ['label' => 'ب', 'is_correct' => true]]]]], 'questions'],
    'no correct option' => [['questions' => [['type' => 'multiple_choice', 'prompt' => 'س', 'options' => [['label' => 'أ', 'is_correct' => false], ['label' => 'ب', 'is_correct' => false]]]]], 'questions'],
    'one option' => [['questions' => [['type' => 'multiple_choice', 'prompt' => 'س', 'options' => [['label' => 'أ', 'is_correct' => true]]]]], 'questions'],
    'video without limits' => [['questions' => [['type' => 'video', 'prompt' => 'س']]], 'questions'],
    'video above the system ceiling' => [['questions' => [['type' => 'video', 'prompt' => 'س', 'max_video_seconds' => 60, 'max_video_mb' => 999999]]], 'questions.0.max_video_mb'],
    'unknown type' => [['questions' => [['type' => 'essay', 'prompt' => 'س']]], 'questions.0.type'],
    'closes before it opens' => [['closes_at' => now()->format('Y-m-d H:i:s')], 'closes_at'],
    'published without questions' => [['questions' => []], 'is_published'],
    'bad slug' => [['slug' => 'Bad Slug'], 'slug'],
]);

it('keeps a draft without questions', function () {
    app(SaveCompetition::class)->handle($this->manager, new Competition, competitionForm(['questions' => [], 'is_published' => false]));

    expect(Competition::sole()->status())->toBe(CompetitionStatus::Draft);
});

it('refuses the action to anyone without the permission', function (RoleName $role) {
    expect(fn () => app(SaveCompetition::class)->handle(userWithRole($role), new Competition, competitionForm()))
        ->toThrow(AuthorizationException::class);

    $competition = Competition::factory()->create();

    expect(fn () => app(SaveCompetition::class)->handle(userWithRole($role), $competition, competitionForm()))
        ->toThrow(AuthorizationException::class);
})->with([RoleName::BoardMember, RoleName::ContentEditor, RoleName::Reviewer, RoleName::Member]);

it('never touches a question or criterion of another competition through a forged id', function () {
    $other = Competition::factory()->withQuestions()->create();
    $otherQuestion = $other->questions->first();
    $otherCriterion = $other->criteria->first();

    $mine = app(SaveCompetition::class)->handle($this->manager, new Competition, competitionForm([
        'criteria' => [['id' => $otherCriterion->id, 'name' => 'مزوّر', 'weight' => 100, 'max_score' => 10]],
        'questions' => [['id' => $otherQuestion->id, 'type' => 'text', 'prompt' => 'مزوّر']],
    ]));

    expect($otherQuestion->fresh()->prompt)->toBe('سؤال نصي تجريبي')
        ->and($otherCriterion->fresh()->name)->toBe('معيار أول')
        ->and($other->questions()->count())->toBe(3)
        ->and($mine->questions->sole()->id)->not->toBe($otherQuestion->id);
});

it('opens and closes by its dates, without a scheduled job', function () {
    $competition = Competition::factory()->create(['opens_at' => now()->addHour(), 'closes_at' => now()->addHours(2)]);

    expect($competition->status())->toBe(CompetitionStatus::Upcoming);

    $this->travel(61)->minutes();
    expect($competition->status())->toBe(CompetitionStatus::Open)->and($competition->isOpen())->toBeTrue();

    $this->travel(60)->minutes();
    expect($competition->status())->toBe(CompetitionStatus::Closed);

    $competition->update(['is_published' => false]);
    expect($competition->status())->toBe(CompetitionStatus::Draft);
});

it('soft deletes and restores a competition, and never deletes it for good', function () {
    $competition = Competition::factory()->create();

    expect($this->manager->can('delete', $competition))->toBeTrue()
        ->and($this->manager->can('forceDelete', $competition))->toBeFalse()
        ->and(userWithRole(RoleName::TechnicalAdmin)->can('forceDelete', $competition))->toBeFalse();

    $competition->delete();
    expect(Competition::withTrashed()->find($competition->id)->trashed())->toBeTrue();
});

it('hides the correct option when a question is turned into an array', function () {
    $competition = Competition::factory()->withQuestions()->create();

    expect($competition->questions[1]->toArray())->not->toHaveKey('correct_option');
});

it('keeps the ids of new questions and criteria when the edit page is saved twice', function () {
    $competition = Competition::factory()->withQuestions()->create();
    $this->actingAs($this->manager);

    $page = Livewire::test(EditCompetition::class, ['record' => $competition->getRouteKey()]);
    $questions = $page->get('data.questions');
    $questions[(string) str()->uuid()] = ['type' => 'text', 'prompt' => 'سؤال مضاف', 'is_required' => true];

    $page->fillForm(['questions' => $questions])->call('save')->assertHasNoFormErrors();
    $ids = $competition->questions()->pluck('id')->all();

    $page->call('save')->assertHasNoFormErrors();

    expect($competition->questions()->pluck('id')->all())->toBe($ids)->and($ids)->toHaveCount(4)
        ->and(array_values(collect($page->get('data.questions'))->firstWhere('prompt', 'سؤال اختيار تجريبي')['options']))->toBe([
            ['label' => 'الخيار الأول', 'is_correct' => false], ['label' => 'الخيار الثاني', 'is_correct' => true],
        ]);
});
