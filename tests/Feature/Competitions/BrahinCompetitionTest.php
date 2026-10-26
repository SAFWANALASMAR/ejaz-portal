<?php

use App\Actions\Competitions\CorrectStudentRegistration;
use App\Actions\Competitions\RunCumulativeTieDraw;
use App\Actions\Competitions\SaveCompetition;
use App\Actions\Competitions\SaveCompetitionRound;
use App\Actions\Competitions\StartAttempt;
use App\Actions\Competitions\SubmitAttempt;
use App\Actions\Competitions\VoidTieDraw;
use App\Enums\RankingScope;
use App\Enums\RoleName;
use App\Enums\StudentGender;
use App\Filament\Resources\Competitions\Pages\EditCompetition;
use App\Filament\Resources\Competitions\RelationManagers\CumulativeRelationManager;
use App\Models\Competition;
use App\Models\CompetitionAttempt;
use App\Models\CompetitionCategory;
use App\Models\CompetitionRegion;
use App\Models\CompetitionRound;
use App\Models\CompetitionSubmission;
use App\Models\User;
use App\Support\CompetitionShortLink;
use App\Support\CumulativeRanking;
use Database\Seeders\BrahinCompetitionSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/*
| مطوية «مسابقة البراهين العلمية» الرسمية (مهمة المسابقة، البند 9): نصوص الصفحة ودليل الدخول حرفياً، والفروع الأربعة
| أيام الخميس، والجنس الإلزامي والجوائز لكل جنس، والترتيب التراكمي بشرط المشاركة في كل الفروع ونطاقه بإعداد، والسحب
| فيه، والرقم المرجعي للمحاولة وصفحة حالتها، والرابط المختصر ورمز QR.
*/

beforeEach(function () {
    config(['competitions.exam.national_id_key' => 'test-hmac-key', 'competitions.student.min_questions' => 1]);

    $this->director = userWithRole(RoleName::ExecutiveDirector);
    $this->competition = Competition::factory()->student()->create(['slug' => 'brahin']);
    $this->round = $this->competition->rounds()->firstOrFail();
});

/**
 * فرع ثانٍ جارٍ الآن بالبنك نفسه.
 */
function secondBranch(Competition $competition): CompetitionRound
{
    $round = new CompetitionRound(['name' => 'الفرع الثاني', 'branch' => 'الحقائق العلمية في علوم الفلك والفضاء', 'sort_order' => 2, 'starts_at' => now()->subMinute(), 'ends_at' => now()->addHours(3), 'question_count' => 1, 'duration_minutes' => 30]);
    $round->competition_id = $competition->id;
    $round->save();
    studentQuestion($round);

    return $round;
}

/**
 * محاولة مسلَّمة بدرجة في فرع.
 */
function branchScore(User $student, CompetitionRound $round, float $score): CompetitionAttempt
{
    $attempt = app(StartAttempt::class)->handle($student, $round->fresh());
    app(SubmitAttempt::class)->handle($student, $attempt);
    $attempt->forceFill(['score' => $score])->save();

    return $attempt;
}

function submissionOf(User $student): CompetitionSubmission
{
    return CompetitionSubmission::query()->where('user_id', $student->id)->sole();
}

it('seeds the brochure competition as an unpublished draft: verbatim texts, four Thursday branches, prizes for each gender and the school field', function () {
    $this->seed(BrahinCompetitionSeeder::class);
    $competition = Competition::query()->where('slug', 'brahin-1448')->sole();
    $rounds = $competition->rounds()->get();
    $zone = (string) config('app.display_timezone');

    expect($competition->is_published)->toBeFalse()
        ->and($competition->student_mode)->toBeTrue()
        ->and($competition->pageText('vision'))->toBe('بناء جيل محب للعلم، متأمل في الكون، قادر على فهم الحقائق وتحليلها، ومعتز بما يقدمه العلم من براهين ودلائل وشواهد جليّة.')
        ->and($competition->pageList('goals'))->toHaveCount(6)
        ->and($competition->pageList('outcomes'))->toHaveCount(4)
        ->and($competition->pageList('rules'))->toHaveCount(4)
        ->and($competition->pageText('partner'))->toBe('وزارة التعليم')
        ->and($rounds->pluck('branch')->all())->toBe(['الحقائق العلمية في الأنفس', 'الحقائق العلمية في علوم الفلك والفضاء', 'الحقائق العلمية في علوم الأرض', 'الحقائق العلمية في علوم البحار'])
        ->and($rounds->every(fn (CompetitionRound $round): bool => $round->starts_at->copy()->setTimezone($zone)->isThursday()))->toBeTrue()
        ->and($rounds[1]->starts_at->diffInDays($rounds[0]->starts_at, true))->toEqual(7.0)
        ->and($competition->closes_at->equalTo($rounds->last()->ends_at))->toBeTrue()
        ->and(array_sum(array_column($competition->prizeRows(), 'male')))->toBe(10000)
        ->and(array_sum(array_column($competition->prizeRows(), 'female')))->toBe(10000)
        ->and($competition->openCategories()->pluck('name')->all())->toBe(['المرحلة المتوسطة', 'المرحلة الثانوية', 'المرحلة الجامعية', 'المهتمون بالثقافة العلمية'])
        ->and($competition->fields()->sole()->only(['label', 'is_required']))->toBe(['label' => 'الجهة التعليمية / المدرسة', 'is_required' => false])
        ->and($competition->short_code)->toMatch('/^[a-z0-9]{6}$/');

    // لا يمس المسابقة إن وُجدت (تعديل المسؤول باقٍ)
    $competition->forceFill(['title' => 'عنوان عدّله المسؤول'])->save();
    $this->seed(BrahinCompetitionSeeder::class);

    expect(Competition::query()->where('slug', 'brahin-1448')->sole()->title)->toBe('عنوان عدّله المسؤول');
});

it('shows the brochure sections, the prizes for boys and girls, and a public entry guide', function () {
    $this->competition->forceFill([
        'page_content' => ['partner' => 'وزارة التعليم', 'vision' => 'رؤية تجريبية للصفحة', 'rules' => ['ضابط أول', 'ضابط ثانٍ'], 'guide_steps' => ['افتح رابط الاختبار الرسمي.', 'اقرأ التعليمات.']],
        'prize_table' => [['male' => 2000, 'female' => 1500], ['male' => 1700, 'female' => 1700]],
    ])->save();
    secondBranch($this->competition);

    $this->get('/competitions/brahin')
        ->assertSeeInOrder([__('competitions.student.page.partner'), 'وزارة التعليم', __('competitions.student.page.vision'), 'رؤية تجريبية للصفحة', __('competitions.student.page.rules'), 'ضابط أول'])
        ->assertSee(__('competitions.student.gender_groups.male'))->assertSee(__('competitions.student.gender_groups.female'))
        ->assertSee('3,700')->assertSee('3,200')
        ->assertSee(__('competitions.student.page.all_branches'), false)
        ->assertSee(route('competitions.student.guide', 'brahin'));

    $this->get('/competitions/brahin/guide')->assertOk()->assertSeeInOrder([__('competitions.student.page.guide_steps'), 'افتح رابط الاختبار الرسمي.', 'اقرأ التعليمات.']);

    // بلا نص للدليل لا صفحة، ولا للمسودة
    $this->competition->forceFill(['page_content' => ['vision' => 'رؤية']])->save();
    $this->get('/competitions/brahin/guide')->assertNotFound();
    $this->competition->forceFill(['page_content' => ['guide_steps' => ['خطوة']], 'is_published' => false])->save();
    $this->get('/competitions/brahin/guide')->assertNotFound();
});

it('requires the gender at registration, stores it, and shows the official rules above the pledge', function () {
    $this->competition->forceFill(['page_content' => ['rules' => ['أن يكون المتسابق سعوديًا أو مقيمًا بإقامة نظامية سارية المفعول.']]])->save();
    $form = [
        'full_name' => 'عبدالله محمد أحمد الغامدي', 'national_id' => studentNationalId(21), 'category_id' => CompetitionCategory::query()->where('name', 'المرحلة المتوسطة')->value('id'),
        'grade' => 'الأول المتوسط', 'region_id' => CompetitionRegion::query()->active()->value('id'), 'pledge' => '1',
    ];
    $student = User::factory()->create();

    $this->actingAs($student)->get('/competitions/brahin/register')
        ->assertSee(__('competitions.student.attributes.gender'))
        ->assertSeeInOrder(['أن يكون المتسابق سعوديًا أو مقيمًا بإقامة نظامية سارية المفعول.', __('competitions.student.pledge')]);

    $this->post('/competitions/brahin/register', $form)->assertSessionHasErrors('gender');
    $this->post('/competitions/brahin/register', [...$form, 'gender' => 'other'])->assertSessionHasErrors('gender');
    $this->post('/competitions/brahin/register', [...$form, 'gender' => 'female'])->assertSessionHasNoErrors();

    expect(submissionOf($student)->gender)->toBe(StudentGender::Female);

    // ويُصحَّح الجنس من أداة تصحيح التسجيل بسبب مسجّل
    $submission = submissionOf($student);
    app(CorrectStudentRegistration::class)->edit($this->director, $submission, [...$submission->only(['full_name', 'category_id', 'grade', 'region_id']), 'gender' => 'male', 'reason' => 'اختير الجنس خطأً في المعمل']);

    expect($submission->fresh()->gender)->toBe(StudentGender::Male);
});

it('gives each attempt a reference shown after submitting, in the account and in the attempt status page', function () {
    studentQuestion($this->round);
    $student = registeredStudent($this->competition);
    $other = registeredStudent($this->competition, 2);

    $this->actingAs($student)->get('/competitions/brahin/rounds/'.$this->round->uuid.'/status')->assertOk()->assertSee(__('competitions.student.status_not_started'));

    $attempt = app(StartAttempt::class)->handle($student, $this->round);
    expect($attempt->reference)->toMatch('/^[A-Z2-9]{10}$/');

    $this->get('/competitions/brahin/rounds/'.$this->round->uuid.'/status')->assertSee(__('competitions.student.status_in_progress'))->assertDontSee($attempt->reference);

    app(SubmitAttempt::class)->handle($student, $attempt);

    $this->get('/competitions/brahin/rounds/'.$this->round->uuid)->assertSee($attempt->reference)->assertSee(__('competitions.student.keep_reference'));
    $this->get('/competitions/brahin/rounds/'.$this->round->uuid.'/status')->assertSee(__('competitions.student.status_submitted'))->assertSee($attempt->reference);
    $this->get('/account/competitions')->assertSee($attempt->reference);

    // صفحة الحالة لمحاولة الطالب نفسه: غيره يرى حالته هو، وغير المسجل 404
    $this->actingAs($other)->get('/competitions/brahin/rounds/'.$this->round->uuid.'/status')->assertSee(__('competitions.student.status_not_started'))->assertDontSee($attempt->reference);
    $this->actingAs(User::factory()->create())->get('/competitions/brahin/rounds/'.$this->round->uuid.'/status')->assertNotFound();
});

it('shows the official guide alerts on the start page in place of the sample instructions', function () {
    studentQuestion($this->round);
    $this->competition->forceFill(['page_content' => ['guide_alerts' => ['لا تشارك رابط الاختبار أو بيانات الدخول مع الآخرين.']]])->save();
    $student = registeredStudent($this->competition);

    $this->actingAs($student)->get('/competitions/brahin/rounds/'.$this->round->uuid)
        ->assertSee('لا تشارك رابط الاختبار أو بيانات الدخول مع الآخرين.')
        ->assertDontSee(__('competitions.student.instructions_heading'));
});

it('ranks the cumulative total of all branches for each gender, leaving out who missed a branch', function () {
    studentQuestion($this->round);
    $second = secondBranch($this->competition);
    [$a, $b, $c] = [registeredStudent($this->competition, 1), registeredStudent($this->competition, 2), registeredStudent($this->competition, 3)];
    $girl = registeredStudent($this->competition, 4, ['gender' => 'female']);

    branchScore($a, $this->round, 6);
    branchScore($a, $second, 4);
    branchScore($b, $this->round, 3);
    branchScore($b, $second, 4);
    branchScore($c, $this->round, 9);
    branchScore($girl, $this->round, 1);
    branchScore($girl, $second, 1);

    $ranked = CumulativeRanking::apply(CompetitionSubmission::query(), $this->competition->fresh())->get()->keyBy('user_id');

    expect((int) $ranked[$a->id]->cumulative_rank)->toBe(1)
        ->and((float) $ranked[$a->id]->cumulative_total)->toBe(10.0)
        ->and((int) $ranked[$b->id]->cumulative_rank)->toBe(2)
        ->and($ranked[$c->id]->cumulative_rank)->toBeNull()
        ->and((int) $ranked[$c->id]->rounds_done)->toBe(1)
        ->and((int) $ranked[$girl->id]->cumulative_rank)->toBe(1);

    $this->actingAs($this->director);
    Livewire::test(CumulativeRelationManager::class, ['ownerRecord' => $this->competition, 'pageClass' => EditCompetition::class])
        ->assertCanSeeTableRecords([submissionOf($a), submissionOf($b), submissionOf($girl)])
        ->assertCanNotSeeTableRecords([submissionOf($c)])
        ->assertTableColumnStateSet('cumulative_rank', 1, submissionOf($a));
});

it('ranks within each category when the competition scope says so', function () {
    studentQuestion($this->round);
    $this->competition->forceFill(['ranking_scope' => RankingScope::Category])->save();
    $middle = CompetitionCategory::query()->where('name', 'المرحلة المتوسطة')->value('id');
    $high = registeredStudent($this->competition, 1);
    $young = registeredStudent($this->competition, 2, ['category_id' => $middle, 'grade' => 'الأول المتوسط']);
    branchScore($high, $this->round, 9);
    branchScore($young, $this->round, 2);

    $ranked = CumulativeRanking::apply(CompetitionSubmission::query(), $this->competition->fresh())->get()->keyBy('user_id');

    expect((int) $ranked[$high->id]->cumulative_rank)->toBe(1)->and((int) $ranked[$young->id]->cumulative_rank)->toBe(1);
});

it('draws among students tied on the cumulative total at the prize boundary, after every branch is published', function () {
    studentQuestion($this->round);
    $second = secondBranch($this->competition);
    $students = collect([1, 2, 3, 4])->map(fn (int $serial) => registeredStudent($this->competition, $serial));
    $students->each(function (User $student, int $index) use ($second): void {
        branchScore($student, $this->round, $index === 0 ? 9 : 5);
        branchScore($student, $second, 5);
    });
    $draw = app(RunCumulativeTieDraw::class);

    expect(fn () => $draw->handle($this->director, $this->competition, StudentGender::Male, null, 2))->toThrow(ValidationException::class);

    $this->competition->rounds()->update(['results_published_at' => now()]);

    expect(fn () => $draw->handle(userWithRole(RoleName::SuperAdmin), $this->competition, StudentGender::Male, null, 2))->toThrow(AuthorizationException::class)
        ->and(fn () => $draw->handle($this->director, $this->competition, StudentGender::Female, null, 2))->toThrow(ValidationException::class);

    [$record, $fresh] = $draw->handle($this->director, $this->competition, StudentGender::Male, null, 2);
    [$again, $repeated] = $draw->handle($this->director, $this->competition, StudentGender::Male, null, 2);

    expect($fresh)->toBeTrue()->and($repeated)->toBeFalse()->and($again->id)->toBe($record->id)
        ->and($record->round_id)->toBeNull()
        ->and($record->gender)->toBe(StudentGender::Male)
        ->and((float) $record->tie_score)->toBe(10.0)
        ->and($record->entrants)->toHaveCount(3)
        ->and($record->winners)->toHaveCount(1)
        ->and(fn () => $draw->handle($this->director, $this->competition, StudentGender::Male, null, 3))->toThrow(ValidationException::class);

    // إلغاء مسجّل مرة واحدة، ثم سحب جديد، ولا يقفل مواعيد المسابقة
    app(VoidTieDraw::class)->handle($this->director, $record, 'أُدخل عدد المراكز خطأً في اللجنة');
    [$redraw, $new] = $draw->handle($this->director, $this->competition, StudentGender::Male, null, 2);

    expect($new)->toBeTrue()->and($redraw->id)->not->toBe($record->id)
        ->and(fn () => app(VoidTieDraw::class)->handle($this->director, $redraw, 'سبب ثانٍ مكتوب بطوله'))->toThrow(ValidationException::class)
        ->and($this->competition->fresh()->scheduleLocked())->toBeFalse();

    $this->artisan('ejaz:verify-draw', ['uuid' => $redraw->uuid])->assertSuccessful();
});

it('asks for the category in a cumulative draw when ranking within each category', function () {
    $this->competition->forceFill(['ranking_scope' => RankingScope::Category])->save();

    expect(fn () => app(RunCumulativeTieDraw::class)->handle($this->director, $this->competition->fresh(), StudentGender::Male, null, 2))->toThrow(ValidationException::class);
});

it('saves the page texts, the prize table and the ranking scope through the competition action, and gives a short link', function () {
    $data = fn (array $overrides = []): array => [
        'title' => 'البراهين', 'slug' => 'brahin-new', 'opens_at' => now()->toDateTimeString(), 'closes_at' => now()->addMonth()->toDateTimeString(), 'student_mode' => true, 'is_published' => false,
        'page_content' => ['vision' => '  رؤية  ', 'goals' => "هدف أول\n\nهدف ثانٍ\n", 'guide_steps' => ['خطوة']],
        'prize_table' => [['male' => '2000', 'female' => '1500']],
        'ranking_scope' => 'category',
        ...$overrides,
    ];

    $competition = app(SaveCompetition::class)->handle($this->director, new Competition, $data());

    expect($competition->page_content)->toEqualCanonicalizing(['vision' => 'رؤية', 'goals' => ['هدف أول', 'هدف ثانٍ'], 'guide_steps' => ['خطوة']])
        ->and($competition->prizeRows())->toBe([['place' => 1, 'male' => 2000, 'female' => 1500]])
        ->and($competition->ranking_scope)->toBe(RankingScope::Category)
        ->and($competition->short_code)->toMatch('/^[a-z0-9]{6}$/');

    $competition->forceFill(['is_published' => true])->save();
    $this->get('/c/'.$competition->short_code)->assertRedirect(route('competitions.show', 'brahin-new'));
    $this->get('/c/zzzzzz')->assertNotFound();

    expect(fn () => app(SaveCompetition::class)->handle($this->director, $competition, $data(['page_content' => ['unknown' => 'نص']])))->toThrow(ValidationException::class)
        ->and(fn () => app(SaveCompetition::class)->handle($this->director, $competition, $data(['prize_table' => [['male' => -1, 'female' => 0]]])))->toThrow(ValidationException::class);

    // ومسودة غير منشورة لا يعمل رابطها
    $competition->forceFill(['is_published' => false])->save();
    $this->get('/c/'.$competition->short_code)->assertNotFound();
});

it('shows the short link and downloads its QR code from the competition page in the panel', function () {
    $this->competition->forceFill(['short_code' => 'abc234'])->save();
    $this->actingAs($this->director);

    expect(CompetitionShortLink::qrSvg(CompetitionShortLink::url($this->competition) ?? ''))->toStartWith('<svg');

    Livewire::test(EditCompetition::class, ['record' => $this->competition->getRouteKey()])
        ->assertActionVisible(TestAction::make('short_link'))
        ->callAction(TestAction::make('download_qr'))
        ->assertFileDownloaded('qr-abc234.svg');
});

it('keeps a branch and its image on the round, refusing an image path outside its folder', function () {
    $round = $this->round->fresh();
    $data = ['name' => 'الفرع الأول', 'branch' => 'الحقائق العلمية في الأنفس', 'starts_at' => $round->starts_at, 'ends_at' => $round->ends_at, 'question_count' => $round->question_count, 'duration_minutes' => 30];

    expect(app(SaveCompetitionRound::class)->handle($this->director, $this->competition, $round, $data)->branch)->toBe('الحقائق العلمية في الأنفس')
        ->and(fn () => app(SaveCompetitionRound::class)->handle($this->director, $this->competition, $round->fresh(), [...$data, 'image_path' => 'partners/../x.png']))->toThrow(ValidationException::class);

    studentQuestion($round->fresh());
    $student = registeredStudent($this->competition);
    $this->actingAs($student)->get('/competitions/brahin')->assertSee('الحقائق العلمية في الأنفس');
});

it('keeps the seeded rounds on Thursdays in Riyadh time whatever the server clock', function () {
    $this->travelTo(Carbon::parse('2026-11-04 22:30:00', 'UTC'));
    $this->seed(BrahinCompetitionSeeder::class);

    $first = Competition::query()->where('slug', 'brahin-1448')->sole()->rounds()->firstOrFail();

    expect($first->starts_at->copy()->setTimezone((string) config('app.display_timezone'))->format('l H:i'))->toBe('Thursday 00:00');
});
