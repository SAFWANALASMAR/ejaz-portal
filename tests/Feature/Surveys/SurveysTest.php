<?php

use App\Actions\Accounts\ExportUserData;
use App\Actions\Surveys\SaveSurvey;
use App\Actions\Surveys\SubmitSurveyResponse;
use App\Actions\Users\AnonymizeUser;
use App\Enums\RoleName;
use App\Enums\SurveyAudience;
use App\Enums\SurveyQuestionType;
use App\Filament\Resources\Surveys\Pages\CreateSurvey;
use App\Filament\Resources\Surveys\Pages\EditSurvey;
use App\Filament\Resources\Surveys\Pages\SurveyResultsPage;
use App\Filament\Resources\Surveys\SurveyResource;
use App\Http\Controllers\Surveys\SurveyController;
use App\Models\Competition;
use App\Models\CompetitionSubmission;
use App\Models\Setting;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Support\SurveyResults;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

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

    $this->survey->update(['allow_guests' => true, 'guest_limit' => 100]);

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

    // ثلاث إجابات: يراها من يدير الاستبيان، وعضو المجلس بعد خمس (الاختبار التالي)
    $this->actingAs($this->manager)->get(SurveyResource::getUrl('results', ['record' => $this->survey]))
        ->assertOk()->assertSee('رائع')->assertDontSee('اسم مستجيب سري');

    $download = Livewire::test(SurveyResultsPage::class, ['record' => $this->survey->getRouteKey()])->callAction('export');
    $download->assertFileDownloaded('survey-'.$this->survey->uuid.'.csv');
});

it('refuses a null byte in a text answer instead of failing on the server', function () {
    answer([$this->scale->id => 3, $this->choice->id => 0, $this->text->id => "abc\0def"], User::factory()->create())
        ->assertSessionHasErrors('answers.'.$this->text->id);

    expect(SurveyResponse::count())->toBe(0);
});

it('caps guest answers per survey and network for the day, counting before saving', function () {
    $this->survey->update(['allow_guests' => true, 'guest_limit' => 100]);
    $key = SurveyController::guestKey($this->survey, '127.0.0.1');

    // المفتاح ببصمة العنوان لا بنصه
    expect($key)->not->toContain('127.0.0.1');

    // تسع عشرة محاولة سابقة اليوم من العنوان نفسه (بكعكات محذوفة)، ومنها المرفوضة: تُحتسب قبل الحفظ
    for ($i = 0; $i < 19; $i++) {
        RateLimiter::increment($key, 86400);
    }

    answer([$this->scale->id => 3, $this->choice->id => 0])->assertSessionHas('status');
    $this->withCookies([])->post('/surveys/'.$this->survey->token, ['answers' => [$this->scale->id => 3, $this->choice->id => 0]])
        ->assertSessionHas('error', __('surveys.public.too_many'));

    expect(SurveyResponse::count())->toBe(1);
});

it('stops a guest who answered from answering again after signing in', function () {
    $this->survey->update(['allow_guests' => true, 'guest_limit' => 100]);
    $response = answer([$this->scale->id => 3, $this->choice->id => 0])->assertSessionHas('status');
    $cookie = collect($response->headers->getCookies())->first(fn ($cookie) => str_starts_with($cookie->getName(), 'survey_'));

    $user = User::factory()->create();
    $this->flushSession();
    $this->actingAs($user)->withCookie($cookie->getName(), '1');

    $this->get('/surveys/'.$this->survey->token)->assertOk()->assertSee(__('surveys.public.already'));
    $this->post('/surveys/'.$this->survey->token, ['answers' => [$this->scale->id => 4, $this->choice->id => 1]])->assertSessionHas('error', __('surveys.public.already'));

    expect(SurveyResponse::count())->toBe(1);
});

it('includes survey answers in the data export with question and option texts', function () {
    $user = User::factory()->create();
    answer([$this->scale->id => 4, $this->choice->id => 1, $this->text->id => 'ملاحظتي'], $user);

    // نص السؤال ونص الخيار، لا المعرّفات
    $exported = app(ExportUserData::class)->handle($user)['survey_responses'][0];
    expect($exported['survey'])->toBe($this->survey->title)
        ->and($exported['answers'])->toBe([
            ['question' => 'ما مدى رضاك؟', 'answer' => 4],
            ['question' => 'كيف عرفت البرنامج؟', 'answer' => 'صديق'],
            ['question' => 'ملاحظاتك', 'answer' => 'ملاحظتي'],
        ]);
});

it('blanks the free text of a deleted account after the retention period, and keeps the numbers', function () {
    $user = User::factory()->create();
    answer([$this->scale->id => 4, $this->choice->id => 1, $this->text->id => 'نص قد يدل على كاتبه'], $user);
    $user->forceFill(['deletion_requested_at' => now()])->save();
    app(AnonymizeUser::class)->handle(userWithRole(RoleName::SuperAdmin), $user->fresh());

    // بلا مدة مضبوطة لا يُحذف شيء، والمدة تُحسب من إخفاء بيانات الحساب
    $this->artisan('ejaz:purge-anonymized-data')->assertSuccessful();
    Setting::put('retention', ['days' => 30]);
    $this->artisan('ejaz:purge-anonymized-data')->assertSuccessful();
    expect(SurveyResponse::sole()->answers)->toHaveKey((string) $this->text->id);

    $this->travel(31)->days();
    $this->artisan('ejaz:purge-anonymized-data')->assertSuccessful();

    $response = SurveyResponse::sole();
    expect($response->answers)->toBe([(string) $this->scale->id => 4, (string) $this->choice->id => 1])
        ->and($response->user_id)->toBeNull()
        ->and(SurveyResults::for($this->survey->fresh()->load('questions'))['questions'][0]['average'])->toBe(4.0)
        ->and(Activity::where('event', 'personal_data_purged')->where('subject_type', $response->getMorphClass())->count())->toBe(1);

    // ومن لم يحذف حسابه لا يُمس
    $kept = User::factory()->create();
    answer([$this->scale->id => 2, $this->choice->id => 0, $this->text->id => 'باقٍ'], $kept);
    $this->artisan('ejaz:purge-anonymized-data')->assertSuccessful();
    expect(SurveyResponse::where('user_id', $kept->id)->sole()->answers)->toHaveKey((string) $this->text->id);
});

it('logs the export of survey results', function () {
    $this->actingAs($this->manager);
    Livewire::test(SurveyResultsPage::class, ['record' => $this->survey->getRouteKey()])->callAction('export');

    expect(Activity::where('event', 'survey_results_exported')->exists())->toBeTrue();
});

it('keeps question ids when the edit page is saved twice, and edits details of an answered survey', function () {
    $this->actingAs($this->manager);
    $ids = $this->survey->questions()->pluck('id')->all();

    $page = Livewire::test(EditSurvey::class, ['record' => $this->survey->getRouteKey()]);
    $page->fillForm(['title' => 'عنوان معدّل'])->call('save')->assertHasNoFormErrors();
    $page->call('save')->assertHasNoFormErrors();

    expect($this->survey->questions()->pluck('id')->all())->toBe($ids)->and($this->survey->fresh()->title)->toBe('عنوان معدّل');

    answer([$this->scale->id => 4, $this->choice->id => 1], User::factory()->create());
    $this->actingAs($this->manager);

    Livewire::test(EditSurvey::class, ['record' => $this->survey->getRouteKey()])
        ->fillForm(['title' => 'عنوان بعد الإجابات'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->survey->fresh()->title)->toBe('عنوان بعد الإجابات')->and($this->survey->questions()->count())->toBe(3);
});

it('shows a survey not yet opened as unavailable, and a deleted one as not found', function () {
    $user = User::factory()->create();

    $this->survey->update(['opens_at' => now()->addDay()]);
    $this->actingAs($user)->get('/surveys/'.$this->survey->token)->assertOk()->assertSee(__('surveys.public.unavailable'));

    $this->survey->update(['opens_at' => null]);
    $this->survey->delete();
    $this->actingAs($user)->get('/surveys/'.$this->survey->token)->assertNotFound();
});

it('lets only assembly members answer a members survey, and refuses guests even when guests are allowed', function () {
    $this->survey->update(['audience' => SurveyAudience::Members, 'allow_guests' => true, 'guest_limit' => 100]);
    $member = userWithRole(RoleName::AssemblyMember);
    $outsider = User::factory()->create();

    answer([$this->scale->id => 4, $this->choice->id => 1], $outsider)->assertSessionHas('error', __('surveys.public.not_audience'));
    answer([$this->scale->id => 4, $this->choice->id => 1])->assertSessionHas('error');
    $this->actingAs($outsider)->get('/surveys/'.$this->survey->token)->assertOk()->assertSee(__('surveys.public.not_audience'));

    expect(SurveyResponse::count())->toBe(0)
        ->and(fn () => app(SubmitSurveyResponse::class)->handle($outsider, $this->survey->load('questions'), [$this->scale->id => 4, $this->choice->id => 1]))
        ->toThrow(AuthorizationException::class);

    answer([$this->scale->id => 4, $this->choice->id => 1], $member)->assertSessionHas('status');
    expect(SurveyResponse::sole()->user_id)->toBe($member->id);
});

it('lets only those who submitted an entry answer a competition survey', function () {
    $competition = Competition::factory()->create();
    $this->survey->subject()->associate($competition)->save();

    $entrant = User::factory()->create();
    CompetitionSubmission::factory()->submitted()->create(['competition_id' => $competition->id, 'user_id' => $entrant->id]);
    $drafter = User::factory()->create();
    CompetitionSubmission::factory()->create(['competition_id' => $competition->id, 'user_id' => $drafter->id]);
    $other = User::factory()->create();
    CompetitionSubmission::factory()->submitted()->create(['user_id' => $other->id]);

    foreach ([$drafter, $other] as $user) {
        answer([$this->scale->id => 4, $this->choice->id => 1], $user)->assertSessionHas('error', __('surveys.public.not_audience'));
    }

    $this->survey->update(['allow_guests' => true, 'guest_limit' => 100]);
    answer([$this->scale->id => 4, $this->choice->id => 1])->assertSessionHas('error');
    expect(SurveyResponse::count())->toBe(0);

    answer([$this->scale->id => 4, $this->choice->id => 1], $entrant)->assertSessionHas('status');
    expect(SurveyResponse::sole()->user_id)->toBe($entrant->id);
});

it('lets the board read the results but only the program manager export them', function () {
    $board = userWithRole(RoleName::BoardMember);
    $this->actingAs($board);

    $this->get(SurveyResource::getUrl('results', ['record' => $this->survey]))->assertOk();
    Livewire::test(SurveyResultsPage::class, ['record' => $this->survey->getRouteKey()])->assertActionHidden('export');

    expect($board->can('surveys.export'))->toBeFalse()
        ->and($this->manager->can('surveys.export'))->toBeTrue()
        ->and(Activity::where('event', 'survey_results_exported')->exists())->toBeFalse();

    // ومن لا يطّلع على الاستبيانات لا يصل إلى صفحة النتائج
    $this->actingAs(userWithRole(RoleName::ContentEditor))->get(SurveyResource::getUrl('results', ['record' => $this->survey]))->assertForbidden();
});

it('neutralizes a formula after leading spaces in the exported answers', function () {
    // الطلب يقص المسافات (TrimStrings)، والإجراء من غير الطلب يُبقي المسافة غير القاطعة، وبرامج الجداول تتجاهلها
    app(SubmitSurveyResponse::class)->handle(User::factory()->create(), $this->survey->load('questions'), [$this->scale->id => 4, $this->choice->id => 0, $this->text->id => "\u{00A0}=HYPERLINK(\"x\")"]);
    $this->actingAs($this->manager);

    $download = Livewire::test(SurveyResultsPage::class, ['record' => $this->survey->getRouteKey()])->callAction('export');
    $csv = base64_decode((string) data_get($download->effects, 'download.content'));

    expect($csv)->toContain("'\u{00A0}=HYPERLINK");
});

it('withholds the breakdown and texts below five responses from those who do not manage the survey', function () {
    foreach (range(1, 4) as $i) {
        answer([$this->scale->id => 5, $this->choice->id => 0, $this->text->id => 'نص-قد-يدل-على-كاتبه-'.$i], User::factory()->create());
    }

    $url = SurveyResource::getUrl('results', ['record' => $this->survey]);

    // عضو المجلس يرى العدد وحده حتى خمس إجابات، فلا يُعرف كاتب نص في استبيان صغير
    $this->actingAs(userWithRole(RoleName::BoardMember))->get($url)
        ->assertOk()->assertSee(__('surveys.results.withheld', ['count' => SurveyResults::MIN_RESPONSES]))->assertDontSee('نص-قد-يدل-على-كاتبه-1');
    $this->actingAs($this->manager)->get($url)->assertOk()->assertSee('نص-قد-يدل-على-كاتبه-1');

    answer([$this->scale->id => 4, $this->choice->id => 1, $this->text->id => 'خامس'], User::factory()->create());

    $this->actingAs(userWithRole(RoleName::BoardMember))->get($url)
        ->assertOk()->assertSee('نص-قد-يدل-على-كاتبه-1')->assertDontSee(__('surveys.results.withheld', ['count' => SurveyResults::MIN_RESPONSES]));
});

it('tells the respondent how the answer is used, at the point of collection', function () {
    $this->actingAs(User::factory()->create())->get('/surveys/'.$this->survey->token)
        ->assertOk()->assertSee(__('surveys.public.privacy'));

    auth()->logout();
    $this->survey->update(['allow_guests' => true, 'guest_limit' => 100]);
    $this->get('/surveys/'.$this->survey->token)->assertOk()->assertSee(__('surveys.public.privacy_guest'));
});

it('counts viewing and answering on their own, so opening the page does not use up the submit limit', function () {
    $user = User::factory()->create();

    foreach (range(1, 12) as $attempt) {
        $this->actingAs($user)->get('/surveys/'.$this->survey->token)->assertOk();
    }

    answer([$this->scale->id => 4, $this->choice->id => 1], $user)->assertSessionHas('status');
});

it('requires a cap on guest answers when guests are allowed, and stops guests once it is reached', function () {
    expect(fn () => app(SaveSurvey::class)->handle($this->manager, new Survey, surveyForm(['allow_guests' => true])))
        ->toThrow(ValidationException::class);

    $this->survey->update(['allow_guests' => true, 'guest_limit' => 2]);

    answer([$this->scale->id => 3, $this->choice->id => 0])->assertSessionHas('status');
    $this->withCookies([])->flushSession();
    answer([$this->scale->id => 4, $this->choice->id => 1])->assertSessionHas('status');
    $this->withCookies([])->flushSession();
    answer([$this->scale->id => 5, $this->choice->id => 2])->assertSessionHas('error', __('surveys.public.guests_full'));

    // أصحاب الحسابات لا يدخلون في السقف
    answer([$this->scale->id => 5, $this->choice->id => 2], User::factory()->create())->assertSessionHas('status');

    expect(SurveyResponse::count())->toBe(3)->and($this->survey->fresh()->guest_responses)->toBe(2);
});

it('limits a text answer to 1000 characters in a survey open to guests', function () {
    $user = User::factory()->create();
    answer([$this->scale->id => 3, $this->choice->id => 0, $this->text->id => str_repeat('ن', 1500)], $user)->assertSessionHasNoErrors();

    $this->survey->update(['allow_guests' => true, 'guest_limit' => 100]);
    answer([$this->scale->id => 3, $this->choice->id => 0, $this->text->id => str_repeat('ن', 1001)], User::factory()->create())
        ->assertSessionHasErrors('answers.'.$this->text->id);
});

it('does not block a second account on the same device of a guest-open survey', function () {
    $this->survey->update(['allow_guests' => true, 'guest_limit' => 100]);

    $first = answer([$this->scale->id => 3, $this->choice->id => 0], User::factory()->create())->assertSessionHas('status');
    expect(collect($first->headers->getCookies())->first(fn ($cookie) => str_starts_with($cookie->getName(), 'survey_')))->toBeNull();

    answer([$this->scale->id => 4, $this->choice->id => 1], User::factory()->create())->assertSessionHas('status');
    expect(SurveyResponse::count())->toBe(2);
});

it('opens the public general survey from /surveys, and finds nothing without one', function () {
    $this->get('/surveys')->assertNotFound();

    $this->survey->update(['audience' => SurveyAudience::General]);
    $this->get('/surveys')->assertRedirect(route('surveys.show', $this->survey->token));

    // استبيان مغلق أو غير منشور لا يُفتح منه
    $this->survey->update(['closes_at' => now()->subMinute()]);
    $this->get('/surveys')->assertNotFound();
});
