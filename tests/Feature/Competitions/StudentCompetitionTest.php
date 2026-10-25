<?php

use App\Actions\Competitions\StartAttempt;
use App\Actions\Competitions\SubmitAttempt;
use App\Actions\Users\AnonymizeUser;
use App\Enums\AttemptStatus;
use App\Enums\PassageKind;
use App\Enums\QuestionType;
use App\Enums\RoleName;
use App\Models\Competition;
use App\Models\CompetitionAttempt;
use App\Models\CompetitionCategory;
use App\Models\CompetitionPassage;
use App\Models\CompetitionRegion;
use App\Models\CompetitionRound;
use App\Models\CompetitionSubmission;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
| مسابقة الطلاب من جهة الطالب (docs/plans/student-competition.md): التسجيل بلا جوال، ورقم الهوية المشفّر الفريد،
| والاختبار في نافذته وحدها، والعشوائية، والعداد والتسليم الآلي، وغياب الإجابة الصحيحة والدرجة قبل النشر.
*/

beforeEach(function () {
    config(['competitions.exam.national_id_key' => 'test-hmac-key']);

    $this->competition = Competition::factory()->student()->create(['slug' => 'students']);
    $this->round = $this->competition->rounds()->firstOrFail();
    $this->secondary = CompetitionCategory::query()->where('name', 'المرحلة الثانوية')->firstOrFail();
    $this->makkah = CompetitionRegion::query()->where('is_active', true)->firstOrFail();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function studentForm(array $overrides = []): array
{
    return [
        'full_name' => 'عبدالله محمد أحمد الغامدي',
        'national_id' => studentNationalId(7),
        'category_id' => test()->secondary->id,
        'grade' => 'الثاني الثانوي',
        'region_id' => test()->makkah->id,
        'guardian_phone' => '0551234567',
        'pledge' => '1',
        ...$overrides,
    ];
}

function startedAttempt(User $user): CompetitionAttempt
{
    return app(StartAttempt::class)->handle($user, test()->round);
}

function unitUrl(int $number): string
{
    return '/competitions/students/rounds/'.test()->round->uuid.'/units/'.$number;
}

/**
 * قيم النموذج كما يرسلها المتصفح: رمز الخيار المعتم لكل سؤال من مفتاحه.
 *
 * @param  array<int, string>  $keys  {معرّف السؤال: مفتاح الخيار}
 * @return array<int, string>
 */
function answerTokens(CompetitionAttempt $attempt, array $keys): array
{
    return collect($keys)->mapWithKeys(fn (string $key, int $id): array => [$id => $attempt->optionToken($id, $key)])->all();
}

it('registers a student with an account that has no phone or email verification, encrypting the national ID and guardian phone', function () {
    $user = User::factory()->create(['phone' => null, 'phone_verified_at' => null]);

    $this->actingAs($user)->get('/competitions/students/register')->assertOk()
        ->assertSee(__('competitions.student.attributes.full_name'))
        ->assertSee('المرحلة الثانوية')
        ->assertSee('الثاني الثانوي')
        ->assertSee($this->makkah->name)
        ->assertDontSee('منطقة الرياض');

    $this->post('/competitions/students/register', studentForm())->assertRedirect('/competitions/students')->assertSessionHasNoErrors();

    $submission = CompetitionSubmission::query()->where('user_id', $user->id)->sole();
    $raw = DB::table('competition_submissions')->where('id', $submission->id)->first();

    expect($submission->national_id)->toBe(studentNationalId(7))
        ->and($submission->guardian_phone)->toBe('+966551234567')
        ->and($submission->registered_at)->not->toBeNull()
        ->and($submission->reference)->not->toBeNull()
        ->and((string) $raw->national_id)->not->toContain(studentNationalId(7))
        ->and((string) $raw->guardian_phone)->not->toContain('551234567')
        ->and(CompetitionSubmission::mask($submission->national_id))->toBe('••••••'.substr(studentNationalId(7), -4));

    $this->get('/competitions/students')->assertSee($submission->reference)->assertSee(__('competitions.student.enter'));
});

it('rejects invalid registration data: short name, bad ID, closed category, wrong grade, inactive region', function (array $overrides, string $field) {
    $user = User::factory()->create();

    if ($field === 'category_id') {
        $this->competition->openCategories()->detach($this->secondary->id);
    }

    $this->actingAs($user)->post('/competitions/students/register', studentForm($overrides))->assertSessionHasErrors($field);

    expect(CompetitionSubmission::query()->where('user_id', $user->id)->exists())->toBeFalse();
})->with([
    'three-part name' => [['full_name' => 'محمد أحمد علي'], 'full_name'],
    'invalid national ID' => [['national_id' => '1234567890'], 'national_id'],
    'category closed for this launch' => [[], 'category_id'],
    'grade from another category' => [['grade' => 'الأول المتوسط'], 'grade'],
    'inactive region' => [['region_id' => 0], 'region_id'],
    'invalid guardian phone' => [['guardian_phone' => '123'], 'guardian_phone'],
    'no pledge' => [['pledge' => null], 'pledge'],
]);

it('rejects an inactive region by its real id', function () {
    $riyadh = CompetitionRegion::query()->where('is_active', false)->firstOrFail();

    $this->actingAs(User::factory()->create())->post('/competitions/students/register', studentForm(['region_id' => $riyadh->id]))->assertSessionHasErrors('region_id');
});

it('lets one national ID register only once in the competition, and one registration per account', function () {
    registeredStudent($this->competition, 7);

    $other = User::factory()->create();
    $this->actingAs($other)->post('/competitions/students/register', studentForm())->assertSessionHasErrors('national_id');

    $first = CompetitionSubmission::query()->firstOrFail();
    $this->actingAs($first->user)->post('/competitions/students/register', studentForm(['national_id' => studentNationalId(8)]))
        ->assertRedirect('/competitions/students')->assertSessionHas('error', __('competitions.student.already_registered'));

    expect(CompetitionSubmission::query()->count())->toBe(1);
});

it('validates and stores custom registration fields added from the panel', function () {
    $school = $this->competition->fields()->create(['label' => 'اسم المدرسة', 'type' => 'text', 'is_required' => true, 'sort_order' => 1]);
    $track = $this->competition->fields()->create(['label' => 'المسار', 'type' => 'select', 'options' => ['عام', 'علمي'], 'is_required' => false, 'sort_order' => 2]);

    $user = User::factory()->create();
    $this->actingAs($user)->get('/competitions/students/register')->assertSee('اسم المدرسة')->assertSee('علمي');

    $this->post('/competitions/students/register', studentForm(['fields' => [$track->id => 'أدبي']]))->assertSessionHasErrors(['fields.'.$school->id, 'fields.'.$track->id]);
    $this->post('/competitions/students/register', studentForm(['fields' => [$school->id => 'ثانوية تجريبية', $track->id => 'علمي']]))->assertSessionHasNoErrors();

    expect(CompetitionSubmission::query()->where('user_id', $user->id)->sole()->extra)->toBe([(string) $school->id => 'ثانوية تجريبية', (string) $track->id => 'علمي']);
});

it('closes registration outside the competition window', function () {
    $this->competition->forceFill(['opens_at' => now()->subWeek(), 'closes_at' => now()->subDay()])->save();

    $this->actingAs(User::factory()->create())->post('/competitions/students/register', studentForm())
        ->assertRedirect('/competitions/students')->assertSessionHas('error', __('competitions.student.registration_closed'));
    $this->get('/competitions/students/register')->assertNotFound();
});

it('keeps the exam closed before and after the round window, and to unregistered users', function () {
    studentQuestion($this->round);
    $student = registeredStudent($this->competition);
    $base = '/competitions/students/rounds/'.$this->round->uuid;

    $this->round->forceFill(['starts_at' => now()->addHour(), 'ends_at' => now()->addHours(2)])->save();
    $this->actingAs($student)->post($base.'/start')->assertSessionHas('error', __('competitions.student.round_closed'));
    $this->get($base)->assertOk()->assertSee('يبدأ الاختبار في')->assertDontSee($base.'/start');

    $this->round->forceFill(['starts_at' => now()->subHours(3), 'ends_at' => now()->subHour()])->save();
    $this->post($base.'/start')->assertSessionHas('error', __('competitions.student.round_closed'));
    $this->get(unitUrl(1))->assertRedirect($base);

    expect(CompetitionAttempt::query()->count())->toBe(0);

    $this->actingAs(User::factory()->create())->get($base)->assertNotFound();
    $this->post($base.'/start')->assertNotFound();
});

it('builds a random order per student, keeping passage questions together under their passage', function () {
    $passage = new CompetitionPassage(['round_id' => $this->round->id, 'kind' => PassageKind::Text, 'title' => 'نص', 'body' => 'فقرة تجريبية للقراءة']);
    $passage->competition_id = $this->competition->id;
    $passage->save();

    $grouped = collect(range(1, 3))->map(fn () => studentQuestion($this->round, ['type' => QuestionType::PassageChoice, 'passage_id' => $passage->id])->id);
    collect(range(1, 7))->each(fn () => studentQuestion($this->round));

    $orders = collect(range(1, 4))->map(function (int $serial) use ($grouped): array {
        $attempt = startedAttempt(registeredStudent($this->competition, $serial));
        $unit = collect($attempt->layout)->first(fn (array $unit): bool => $unit['passage'] !== null);

        expect(collect($unit['questions'])->pluck('id')->sort()->values()->all())->toBe($grouped->sort()->values()->all())
            ->and(count($attempt->questionIds()))->toBe(10)
            ->and(count($attempt->layout))->toBe(8);

        return [$attempt->questionIds(), collect($attempt->layout)->flatMap(fn ($u) => collect($u['questions'])->pluck('options'))->all()];
    });

    // أربعة طلاب بترتيب واحد للأسئلة والخيارات معاً: احتمال لا يُذكر
    expect($orders->unique()->count())->toBeGreaterThan(1);
});

it('takes only the round question count from a larger bank, and only the student category and shared questions', function () {
    $middle = CompetitionCategory::query()->where('name', 'المرحلة المتوسطة')->firstOrFail();
    collect(range(1, 12))->each(fn () => studentQuestion($this->round));
    $other = studentQuestion($this->round, ['category_id' => $middle->id]);
    $own = studentQuestion($this->round, ['category_id' => $this->secondary->id]);
    $this->round->forceFill(['question_count' => 13])->save();

    $ids = startedAttempt(registeredStudent($this->competition))->questionIds();

    expect($ids)->toHaveCount(13)->not->toContain($other->id);
});

it('shows a visible timer, never the correct answer or option keys, and saves answers by opaque tokens', function () {
    $question = studentQuestion($this->round, ['prompt' => 'سؤال الصفحة']);
    $student = registeredStudent($this->competition);
    $this->actingAs($student)->post('/competitions/students/rounds/'.$this->round->uuid.'/start');
    $attempt = CompetitionAttempt::query()->sole();

    $page = $this->get(unitUrl(1))->assertOk()->assertSee('سؤال الصفحة')->assertSee('data-exam-timer', false)->assertSee(__('competitions.student.time_left'));

    expect($page->getContent())->not->toContain('value="b"')->not->toContain('value="a"')->not->toContain('correct');

    $this->post(unitUrl(1), ['answers' => [$question->id => 'b'], 'go' => 'next'])->assertSessionHasErrors('answers');
    $this->post(unitUrl(1), ['answers' => answerTokens($attempt, [$question->id => 'b']), 'go' => 'review'])->assertRedirect('/competitions/students/rounds/'.$this->round->uuid.'/review');

    expect($attempt->fresh()->answer($question->id))->toBe('b');
});

it('resumes where the student stopped after a disconnection, keeping the original deadline', function () {
    $first = studentQuestion($this->round);
    studentQuestion($this->round);
    $this->round->forceFill(['question_count' => 2])->save();
    $student = registeredStudent($this->competition);
    $attempt = startedAttempt($student);
    $deadline = $attempt->deadline_at->toIso8601String();

    $firstUnit = collect($attempt->layout)->search(fn ($unit) => $unit['questions'][0]['id'] === $first->id) + 1;
    $this->actingAs($student)->post(unitUrl($firstUnit), ['answers' => answerTokens($attempt, [$first->id => 'a']), 'go' => 'next']);

    $this->travel(10)->minutes();
    $this->post('/competitions/students/rounds/'.$this->round->uuid.'/start');

    $other = 3 - $firstUnit;
    $this->get('/competitions/students/rounds/'.$this->round->uuid)->assertRedirect(unitUrl($other));

    expect(CompetitionAttempt::query()->count())->toBe(1)
        ->and($attempt->fresh()->deadline_at->toIso8601String())->toBe($deadline)
        ->and($attempt->fresh()->secondsLeft())->toBeLessThanOrEqual(20 * 60);
});

it('caps the deadline at the end of the round window', function () {
    studentQuestion($this->round);
    $this->round->forceFill(['ends_at' => now()->addMinutes(10)])->save();

    $attempt = startedAttempt(registeredStudent($this->competition));

    expect($attempt->deadline_at->timestamp)->toBe($this->round->ends_at->timestamp);
});

it('rejects answers after the time is up and submits the attempt automatically', function () {
    $question = studentQuestion($this->round);
    $student = registeredStudent($this->competition);
    $attempt = startedAttempt($student);

    $this->travel(31)->minutes();

    $this->actingAs($student)->post(unitUrl(1), ['answers' => answerTokens($attempt, [$question->id => 'b']), 'go' => 'next'])
        ->assertSessionHas('error', __('competitions.student.time_up'));

    $fresh = $attempt->fresh();
    expect($fresh->status)->toBe(AttemptStatus::Submitted)
        ->and($fresh->auto_submitted)->toBeTrue()
        ->and($fresh->answer($question->id))->toBeNull()
        ->and($fresh->submitted_at->timestamp)->toBe($attempt->deadline_at->timestamp);

    $this->get('/competitions/students/rounds/'.$this->round->uuid)->assertOk()->assertSee(__('competitions.student.received'));
});

it('accepts the last answer within the network grace, then the browser auto-submits it', function () {
    $question = studentQuestion($this->round);
    $student = registeredStudent($this->competition);
    $attempt = startedAttempt($student);

    $this->travel(30 * 60 + 5)->seconds();

    $this->actingAs($student)->post(unitUrl(1), ['answers' => answerTokens($attempt, [$question->id => 'b']), 'go' => 'submit', 'auto' => '1']);

    $fresh = $attempt->fresh();
    expect($fresh->status)->toBe(AttemptStatus::Submitted)->and($fresh->auto_submitted)->toBeTrue()->and((float) $fresh->score)->toBe(1.0);
});

it('closes expired attempts from the scheduled command, grading what was saved', function () {
    $question = studentQuestion($this->round, ['points' => 3]);
    $student = registeredStudent($this->competition);
    $attempt = startedAttempt($student);
    $this->actingAs($student)->post(unitUrl(1), ['answers' => answerTokens($attempt, [$question->id => 'b']), 'go' => 'next']);
    $running = startedAttempt(registeredStudent($this->competition, 2));

    $this->travel(31)->minutes();
    $this->artisan('ejaz:close-expired-attempts')->assertSuccessful();

    expect($attempt->fresh()->status)->toBe(AttemptStatus::Submitted)
        ->and((float) $attempt->fresh()->score)->toBe(3.0)
        ->and((float) $attempt->fresh()->max_score)->toBe(3.0)
        ->and($running->fresh()->status)->toBe(AttemptStatus::Submitted);
});

it('grades choice and true/false questions automatically and shows only "answers received" after submission', function () {
    $mcq = studentQuestion($this->round, ['points' => 2]);
    $tf = studentQuestion($this->round, ['type' => QuestionType::TrueFalse, 'options' => [['key' => 't', 'label' => 'صح'], ['key' => 'f', 'label' => 'خطأ']], 'correct_option' => 'f']);
    $wrong = studentQuestion($this->round);
    $student = registeredStudent($this->competition);
    $attempt = startedAttempt($student);

    foreach ($attempt->layout as $index => $unit) {
        $id = $unit['questions'][0]['id'];
        $key = match ($id) {
            $mcq->id => 'b',
            $tf->id => 'f',
            $wrong->id => 'a',
        };
        $this->actingAs($student)->post(unitUrl($index + 1), ['answers' => answerTokens($attempt, [$id => $key]), 'go' => 'next']);
    }

    $this->post('/competitions/students/rounds/'.$this->round->uuid.'/submit')->assertRedirect('/competitions/students/rounds/'.$this->round->uuid);

    $fresh = $attempt->fresh();
    expect((float) $fresh->score)->toBe(3.0)->and((float) $fresh->max_score)->toBe(4.0)->and($fresh->auto_submitted)->toBeFalse();

    $received = $this->get('/competitions/students/rounds/'.$this->round->uuid)->assertOk()->assertSee(__('competitions.student.received'));
    expect($received->getContent())->not->toContain(__('competitions.student.your_score', ['score' => '3', 'max' => '4']));

    // متكرر الأمان: التسليم الثاني لا يغيّر شيئاً، ولا تعديل بعده
    $this->post('/competitions/students/rounds/'.$this->round->uuid.'/submit');
    $this->post(unitUrl(1), ['answers' => [], 'go' => 'next'])->assertSessionHas('error', __('competitions.student.already_submitted'));
    expect($attempt->fresh()->submitted_at->equalTo($fresh->submitted_at))->toBeTrue();
});

it('never shows the score before the results are published, and shows it in the account after', function () {
    $question = studentQuestion($this->round, ['points' => 5]);
    $student = registeredStudent($this->competition);
    $attempt = startedAttempt($student);
    $this->actingAs($student)->post(unitUrl(1), ['answers' => answerTokens($attempt, [$question->id => 'b']), 'go' => 'submit']);

    $score = __('competitions.student.your_score', ['score' => '5', 'max' => '5']);
    $this->get('/account/competitions')->assertOk()->assertSee(__('competitions.student.received_short'))->assertDontSee($score);
    $this->get('/competitions/students')->assertDontSee($score);

    $this->round->forceFill(['results_published_at' => now()])->save();

    $this->get('/account/competitions')->assertSee($score);
});

it('does not let a student reach another student attempt or answers', function () {
    $question = studentQuestion($this->round);
    $owner = registeredStudent($this->competition, 1);
    $attempt = startedAttempt($owner);
    $this->actingAs($owner)->post(unitUrl(1), ['answers' => answerTokens($attempt, [$question->id => 'b']), 'go' => 'next']);

    $intruder = registeredStudent($this->competition, 2);

    // المتطفل يرى محاولته هو وحدها: لم يبدأ، فصفحة البدء، ولا وحدة له
    $this->actingAs($intruder)->get(unitUrl(1))->assertRedirect('/competitions/students/rounds/'.$this->round->uuid);
    $this->post(unitUrl(1), ['answers' => answerTokens($attempt, [$question->id => 'a']), 'go' => 'next'])->assertNotFound();

    expect(fn () => app(SubmitAttempt::class)->handle($intruder, $attempt))->toThrow(AuthorizationException::class);
    expect($attempt->fresh()->answer($question->id))->toBe('b')->and($attempt->fresh()->isInProgress())->toBeTrue();
});

it('opens a qualified-only round to students marked qualified in the previous round', function () {
    studentQuestion($this->round);
    $second = new CompetitionRound(['name' => 'المرحلة الثانية', 'sort_order' => 2, 'starts_at' => now()->subMinute(), 'ends_at' => now()->addHour(), 'question_count' => 1, 'duration_minutes' => 30, 'qualified_only' => true]);
    $second->competition_id = $this->competition->id;
    $second->save();
    studentQuestion($second);

    $qualified = registeredStudent($this->competition, 1);
    $other = registeredStudent($this->competition, 2);
    $attempt = startedAttempt($qualified);
    app(SubmitAttempt::class)->handle($qualified, $attempt);
    $attempt->forceFill(['qualified_at' => now()])->save();

    expect(app(StartAttempt::class)->handle($qualified, $second))->toBeInstanceOf(CompetitionAttempt::class)
        ->and(fn () => app(StartAttempt::class)->handle($other, $second))->toThrow(AuthorizationException::class);
});

it('keeps exam routes limited per account, so students behind one school network do not share a limit', function () {
    studentQuestion($this->round);

    foreach (range(1, 15) as $serial) {
        $student = registeredStudent($this->competition, $serial);
        $this->actingAs($student)->withServerVariables(['REMOTE_ADDR' => '10.20.30.40'])
            ->post('/competitions/students/rounds/'.$this->round->uuid.'/start')->assertRedirect();
    }

    expect(CompetitionAttempt::query()->count())->toBe(15);
});

it('clears the student identity, guardian phone, custom fields and certificate when the account is anonymized', function () {
    Storage::fake('local');
    $field = $this->competition->fields()->create(['label' => 'اسم المدرسة', 'type' => 'text', 'is_required' => false, 'sort_order' => 1]);
    $student = registeredStudent($this->competition, 3, ['guardian_phone' => '0551234567', 'fields' => [$field->id => 'مدرسة تجريبية']]);
    $submission = CompetitionSubmission::query()->where('user_id', $student->id)->sole();
    Storage::disk('local')->put('competition-certificates/y.pdf', '%PDF-1.4');
    $submission->forceFill(['certificate_path' => 'competition-certificates/y.pdf'])->save();

    app(AnonymizeUser::class)->handle(userWithRole(RoleName::SuperAdmin), $student);

    $fresh = $submission->fresh();
    expect($fresh->national_id)->toBeNull()
        ->and($fresh->guardian_phone)->toBeNull()
        ->and($fresh->extra)->toBeNull()
        ->and($fresh->certificate_path)->toBeNull()
        ->and($fresh->reference)->not->toBeNull()
        ->and(Storage::disk('local')->exists('competition-certificates/y.pdf'))->toBeFalse();
});

it('lets the owner download the certificate, and nobody else', function () {
    $student = registeredStudent($this->competition);
    $submission = CompetitionSubmission::query()->where('user_id', $student->id)->sole();
    Storage::fake('local');
    Storage::disk('local')->put('competition-certificates/x.pdf', '%PDF-1.4');
    $submission->forceFill(['certificate_path' => 'competition-certificates/x.pdf', 'certificate_issued_at' => now()])->save();

    $this->actingAs($student)->get('/account/competitions')->assertSee(__('competitions.student.certificate'));
    $this->get('/account/competitions/'.$submission->uuid.'/certificate')->assertOk()->assertDownload('certificate-'.$submission->reference.'.pdf');

    $this->actingAs(registeredStudent($this->competition, 2))->get('/account/competitions/'.$submission->uuid.'/certificate')->assertNotFound();
});
