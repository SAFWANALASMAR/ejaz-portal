<?php

use App\Actions\Surveys\SaveSurvey;
use App\Enums\RoleName;
use App\Enums\SurveyQuestionType;
use App\Filament\Resources\Surveys\Pages\CreateSurvey;
use App\Filament\Resources\Surveys\Pages\SurveyResultsPage;
use App\Filament\Resources\Surveys\SurveyResource;
use App\Models\Competition;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Support\SurveyResults;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->manager = userWithRole(RoleName::ProgramManager);
    $this->survey = Survey::factory()->withQuestions()->create();
    [$this->scale, $this->choice, $this->text] = $this->survey->questions->all();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function surveyForm(array $overrides = []): array
{
    return [
        'title' => 'استبيان تجريبي',
        'audience' => 'trainees',
        'is_published' => true,
        'allow_guests' => false,
        'questions' => [
            ['type' => 'scale', 'prompt' => 'رضاك عن البرنامج', 'is_required' => true],
            ['type' => 'choice', 'prompt' => 'أفضل جزء', 'is_required' => true, 'options' => [['label' => 'المحاضرات'], ['label' => 'الورش']]],
            ['type' => 'text', 'prompt' => 'اقتراحاتك', 'is_required' => false],
        ],
        ...$overrides,
    ];
}

function answer(array $answers, ?User $user = null): TestResponse
{
    $test = test();
    $user === null ? auth()->logout() : $test->actingAs($user);

    return $test->post('/surveys/'.$test->survey->token, ['answers' => $answers]);
}

it('opens the surveys screen to the program manager, lets the board look only, and keeps others out', function () {
    $this->actingAs($this->manager)->get(SurveyResource::getUrl('index'))->assertOk();
    $this->actingAs($this->manager)->get(SurveyResource::getUrl('create'))->assertOk();
    $this->actingAs(userWithRole(RoleName::BoardMember))->get(SurveyResource::getUrl('index'))->assertOk();
    $this->actingAs(userWithRole(RoleName::BoardMember))->get(SurveyResource::getUrl('create'))->assertForbidden();
    $this->actingAs(userWithRole(RoleName::BoardMember))->get(SurveyResource::getUrl('results', ['record' => $this->survey]))->assertOk();
    $this->actingAs(userWithRole(RoleName::ContentEditor))->get(SurveyResource::getUrl('index'))->assertForbidden();
    $this->actingAs(userWithRole(RoleName::Reviewer))->get(SurveyResource::getUrl('results', ['record' => $this->survey]))->assertForbidden();
});

it('creates a survey linked to a competition with its questions, and a random link token', function () {
    $competition = Competition::factory()->create();
    $this->actingAs($this->manager);

    Livewire::test(CreateSurvey::class)
        ->fillForm(surveyForm(['competition_id' => $competition->id]))
        ->call('create')
        ->assertHasNoFormErrors();

    $survey = Survey::where('title', 'استبيان تجريبي')->sole();

    expect($survey->token)->toMatch('/^[A-Za-z0-9]{32}$/')
        ->and($survey->subject->is($competition))->toBeTrue()
        ->and($survey->questions->pluck('type')->all())->toBe([SurveyQuestionType::Scale, SurveyQuestionType::Choice, SurveyQuestionType::Text])
        ->and($survey->questions[1]->optionList())->toBe(['المحاضرات', 'الورش']);
});

it('refuses a choice question with fewer than two options, publishing without questions, and outsiders', function () {
    expect(fn () => app(SaveSurvey::class)->handle($this->manager, new Survey, surveyForm(['questions' => [['type' => 'choice', 'prompt' => 'س', 'options' => [['label' => 'واحد']]]]])))
        ->toThrow(ValidationException::class)
        ->and(fn () => app(SaveSurvey::class)->handle($this->manager, new Survey, surveyForm(['questions' => []])))
        ->toThrow(ValidationException::class, __('surveys.validation.needs_questions'))
        ->and(fn () => app(SaveSurvey::class)->handle(userWithRole(RoleName::BoardMember), new Survey, surveyForm()))
        ->toThrow(AuthorizationException::class);
});

it('locks the questions after the first response', function () {
    answer([$this->scale->id => 4, $this->choice->id => 0], User::factory()->create());

    expect(fn () => app(SaveSurvey::class)->handle($this->manager, $this->survey, surveyForm()))
        ->toThrow(ValidationException::class, __('surveys.validation.questions_locked'));
});

it('lets an account answer once, even when two requests race', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/surveys/'.$this->survey->token)->assertOk()->assertSee('ما مدى رضاك؟');
    answer([$this->scale->id => 5, $this->choice->id => 2, $this->text->id => 'ممتاز'], $user)->assertSessionHas('status', __('surveys.public.thanks'));
    answer([$this->scale->id => 1, $this->choice->id => 0], $user)->assertSessionHasErrors('answers');
    $this->actingAs($user)->get('/surveys/'.$this->survey->token)->assertSee(__('surveys.public.already'));

    expect(SurveyResponse::count())->toBe(1)
        ->and(SurveyResponse::sole()->answers)->toBe([(string) $this->scale->id => 5, (string) $this->choice->id => 2, (string) $this->text->id => 'ممتاز']);

    // سباق: طلب ثانٍ مرّ من فحص الوجود، فيرده القيد الفريد
    $other = User::factory()->create();
    $first = new SurveyResponse;
    $first->forceFill(['survey_id' => $this->survey->id, 'user_id' => $other->id, 'answers' => [], 'submitted_at' => now()])->save();
    expect(fn () => (new SurveyResponse)->forceFill(['survey_id' => $this->survey->id, 'user_id' => $other->id, 'answers' => [], 'submitted_at' => now()])->save())
        ->toThrow(UniqueConstraintViolationException::class);
});

it('validates answers by question type and requires the required ones', function (array $answers, string $field) {
    answer($answers, User::factory()->create())->assertSessionHasErrors($field);

    expect(SurveyResponse::count())->toBe(0);
})->with([
    'scale above 5' => fn () => [[test()->scale->id => 6, test()->choice->id => 0], 'answers.'.test()->scale->id],
    'unknown option' => fn () => [[test()->scale->id => 3, test()->choice->id => 9], 'answers.'.test()->choice->id],
    'missing required' => fn () => [[test()->choice->id => 0], 'answers.'.test()->scale->id],
]);

it('asks guests to log in unless the survey allows them, and stops a guest repeating from the same device', function () {
    $this->get('/surveys/'.$this->survey->token)->assertRedirect('/login');
    answer([$this->scale->id => 3, $this->choice->id => 1])->assertSessionHas('error');

    $this->survey->update(['allow_guests' => true]);

    $response = answer([$this->scale->id => 3, $this->choice->id => 1])->assertSessionHas('status');
    $cookie = collect($response->headers->getCookies())->first(fn ($cookie) => str_starts_with($cookie->getName(), 'survey_'));

    $this->withCookie($cookie->getName(), '1')->post('/surveys/'.$this->survey->token, ['answers' => [$this->scale->id => 1, $this->choice->id => 0]])->assertSessionHas('error');

    expect(SurveyResponse::count())->toBe(1)->and(SurveyResponse::sole()->user_id)->toBeNull();
});

it('refuses answers to an unpublished, closed or unknown survey', function () {
    $user = User::factory()->create();

    $this->survey->update(['closes_at' => now()->subMinute()]);
    $this->actingAs($user)->get('/surveys/'.$this->survey->token)->assertOk()->assertSee(__('surveys.public.unavailable'));
    answer([$this->scale->id => 3, $this->choice->id => 1], $user)->assertSessionHas('error');

    $this->survey->update(['is_published' => false, 'closes_at' => null]);
    $this->actingAs($user)->get('/surveys/'.$this->survey->token)->assertNotFound();
    $this->get('/surveys/'.str_repeat('a', 32))->assertNotFound();

    expect(SurveyResponse::count())->toBe(0);
});

it('aggregates results without the respondents, and exports them', function () {
    foreach ([[5, 0, 'رائع'], [3, 0, null], [4, 2, '=cmd|calc']] as [$scale, $choice, $text]) {
        answer(array_filter([$this->scale->id => $scale, $this->choice->id => $choice, $this->text->id => $text], fn ($value) => $value !== null), User::factory()->create(['name' => 'اسم مستجيب سري']));
    }

    $results = SurveyResults::for($this->survey->fresh()->load('questions'));

    expect($results['responses'])->toBe(3)
        ->and($results['questions'][0]['average'])->toBe(4.0)
        ->and($results['questions'][0]['counts'])->toBe([1 => 0, 2 => 0, 3 => 1, 4 => 1, 5 => 1])
        ->and($results['questions'][1]['counts'])->toBe([0 => 2, 1 => 0, 2 => 1])
        ->and($results['questions'][2]['answered'])->toBe(2);

    $this->actingAs(userWithRole(RoleName::BoardMember))->get(SurveyResource::getUrl('results', ['record' => $this->survey]))
        ->assertOk()->assertSee('رائع')->assertDontSee('اسم مستجيب سري');

    $this->actingAs($this->manager);
    $download = Livewire::test(SurveyResultsPage::class, ['record' => $this->survey->getRouteKey()])->callAction('export');
    $download->assertFileDownloaded('survey-'.$this->survey->uuid.'.csv');
});
