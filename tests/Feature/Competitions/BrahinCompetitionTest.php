<?php

use App\Actions\Competitions\CorrectStudentRegistration;
use App\Actions\Competitions\PublishRoundResults;
use App\Actions\Competitions\RevealStudentIdentity;
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
use App\Filament\Resources\Competitions\RelationManagers\RoundsRelationManager;
use App\Models\Competition;
use App\Models\CompetitionAttempt;
use App\Models\CompetitionCategory;
use App\Models\CompetitionDraw;
use App\Models\CompetitionRegion;
use App\Models\CompetitionRound;
use App\Models\CompetitionSubmission;
use App\Models\User;
use App\Support\AttemptLayout;
use App\Support\CompetitionShortLink;
use App\Support\CumulativeRanking;
use App\Support\DrawSelector;
use App\Support\RoundQuestions;
use App\Support\StudentResultsSheet;
use Database\Seeders\BrahinCompetitionSeeder;
use Database\Seeders\MenuSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;

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

// ── مراجعة المشرف على المطوية (0ebf0c25)

it('lets a whole school behind one address open the short link at once', function () {
    $this->competition->forceFill(['short_code' => 'school2'])->save();

    foreach (range(1, 300) as $i) {
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])->get('/c/school2')->assertRedirect(route('competitions.show', 'brahin'));
    }
});

it('refuses to start a branch whose maximum score differs between categories when ranking all categories together', function () {
    $middle = CompetitionCategory::query()->where('name', 'المرحلة المتوسطة')->value('id');
    $secondary = CompetitionCategory::query()->where('name', 'المرحلة الثانوية')->value('id');
    $this->competition->openCategories()->sync([$middle, $secondary]);
    studentQuestion($this->round, ['category_id' => $middle, 'points' => 1]);
    studentQuestion($this->round, ['category_id' => $secondary, 'points' => 3]);
    $student = registeredStudent($this->competition);

    expect(fn () => app(StartAttempt::class)->handle($student, $this->round->fresh()))->toThrow(ValidationException::class, __('competitions.student.mixed_category_max'));

    $this->actingAs($this->director);
    Livewire::test(RoundsRelationManager::class, ['ownerRecord' => $this->competition, 'pageClass' => EditCompetition::class])
        ->assertSee(__('competitions.student.bank_mixed_max', ['details' => 'المرحلة المتوسطة 1، المرحلة الثانوية 3']));

    // والترتيب لكل فئة على حدة لا يقارن بين الفئات، فيبدأ
    $this->competition->forceFill(['ranking_scope' => RankingScope::Category])->save();

    expect(app(StartAttempt::class)->handle($student, $this->round->fresh())->max_score)->toEqual(3);
});

it('leaves a cumulative group unranked, with a panel warning, when its maximum scores differ', function () {
    studentQuestion($this->round);
    $a = registeredStudent($this->competition, 1);
    $b = registeredStudent($this->competition, 2);
    branchScore($a, $this->round, 1);
    branchScore($b, $this->round, 1)->forceFill(['max_score' => 5])->save();

    $ranked = CumulativeRanking::apply(CompetitionSubmission::query(), $this->competition->fresh())->get()->keyBy('user_id');

    expect($ranked[$a->id]->cumulative_rank)->toBeNull()
        ->and($ranked[$b->id]->cumulative_rank)->toBeNull()
        ->and(CumulativeRanking::hasMixedMax($this->competition))->toBeTrue();

    $this->actingAs($this->director);
    Livewire::test(CumulativeRelationManager::class, ['ownerRecord' => $this->competition, 'pageClass' => EditCompetition::class])
        ->assertSee(__('competitions.student.cumulative_mixed_max'));
});

it('leaves registrations without a gender out of the prize ranking', function () {
    studentQuestion($this->round);
    $student = registeredStudent($this->competition);
    branchScore($student, $this->round, 1);
    submissionOf($student)->forceFill(['gender' => null])->save();

    $row = CumulativeRanking::apply(CompetitionSubmission::query(), $this->competition->fresh())->sole();

    expect((bool) $row->eligible)->toBeFalse()->and($row->cumulative_rank)->toBeNull();
});

it('locks the ranking scope and the prize table once a cumulative draw ran or every branch is published', function () {
    studentQuestion($this->round);
    $this->competition->forceFill(['prize_table' => [['male' => 100, 'female' => 100]]])->save();
    $data = fn (array $overrides = []): array => [
        'title' => $this->competition->title, 'slug' => 'brahin', 'opens_at' => $this->competition->opens_at, 'closes_at' => $this->competition->closes_at,
        'ranking_scope' => 'all', 'prize_table' => [['male' => '100', 'female' => '100']], ...$overrides,
    ];

    // قبل النشر يتغير
    expect(app(SaveCompetition::class)->handle($this->director, $this->competition->fresh(), $data(['prize_table' => [['male' => 200, 'female' => 200]]]))->prizeRows()[0]['male'])->toBe(200);
    $this->competition->fresh()->forceFill(['prize_table' => [['male' => 100, 'female' => 100]]])->save();

    $this->round->forceFill(['results_published_at' => now()])->save();
    $competition = $this->competition->fresh();

    expect($competition->cumulativeLocked())->toBeTrue()
        ->and(fn () => app(SaveCompetition::class)->handle($this->director, $competition, $data(['ranking_scope' => 'category'])))->toThrow(ValidationException::class)
        ->and(fn () => app(SaveCompetition::class)->handle($this->director, $competition, $data(['prize_table' => [['male' => 500, 'female' => 100]]])))->toThrow(ValidationException::class)
        ->and(app(SaveCompetition::class)->handle($this->director, $competition, $data(['title' => 'عنوان آخر']))->title)->toBe('عنوان آخر');
});

it('reads the ranking scope from the locked competition when drawing', function () {
    $stale = $this->competition->fresh();
    Competition::query()->whereKey($stale->id)->update(['ranking_scope' => RankingScope::Category->value]);

    expect(fn () => app(RunCumulativeTieDraw::class)->handle($this->director, $stale, StudentGender::Male, null, 2))
        ->toThrow(ValidationException::class, __('competitions.student.cumulative_category_required'));
});

it('merges a seeded fifth interested category into the old one, moving what uses it', function () {
    $migration = require database_path('migrations/2026_10_27_100100_rename_science_enthusiasts_category.php');
    $new = CompetitionCategory::query()->where('name', 'المهتمون بالثقافة العلمية')->sole();
    $old = CompetitionCategory::query()->forceCreate(['name' => 'المهتمون', 'sort_order' => 4]);
    // الجديدة مفتوحة في المسابقة، ولها تسجيل وسؤال، والقديمة مفتوحة أيضاً
    $this->competition->openCategories()->attach($old->id);
    $student = registeredStudent($this->competition, 1, ['category_id' => $new->id, 'grade' => null]);
    $question = studentQuestion($this->round, ['category_id' => $new->id]);

    $migration->up();

    expect(CompetitionCategory::query()->where('name', 'like', 'المهتمون%')->pluck('id')->all())->toBe([$old->id])
        ->and($old->fresh()->name)->toBe('المهتمون بالثقافة العلمية')
        ->and(submissionOf($student)->category_id)->toBe($old->id)
        ->and($question->fresh()->category_id)->toBe($old->id)
        ->and(DB::table('competition_category_competition')->where('competition_id', $this->competition->id)->where('category_id', $old->id)->count())->toBe(1);
});

it('ranks tied scores equally in the results export, and shows the grand prize total', function () {
    studentQuestion($this->round);
    collect([[1, 9], [2, 9], [3, 5]])->each(fn (array $row) => branchScore(registeredStudent($this->competition, $row[0]), $this->round, $row[1]));
    $this->competition->forceFill(['prize_table' => [['male' => 2000, 'female' => 2000], ['male' => 1700, 'female' => 1700]]])->save();

    $path = tempnam(sys_get_temp_dir(), 'r').'.xlsx';
    file_put_contents($path, TestResponse::fromBaseResponse(StudentResultsSheet::download($this->director, $this->competition, $this->competition->attempts()->getQuery()))->streamedContent());
    $reader = new Reader;
    $reader->open($path);
    $ranks = [];

    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $ranks[] = (string) $row->toArray()[0];
        }
    }

    $reader->close();

    expect(array_slice($ranks, 1))->toBe(['1', '1', '3']);

    $this->get('/competitions/brahin')->assertSee(__('competitions.student.page.prizes_grand_total', ['amount' => '7,400']));
});

// ── بعد دمج ملاحظات المدير التنفيذي (#66) ومراجعة المشرف على 0b788d7f

it('lets a student with a username alone, no email and no phone, register in the competition and take a branch', function () {
    studentQuestion($this->round);

    $this->post('/register', [
        'name' => 'طالب المعمل', 'username' => 'lab.student7', 'password' => 'Thursday-Exam-Key-2026', 'password_confirmation' => 'Thursday-Exam-Key-2026', 'privacy' => '1',
    ])->assertSessionHasNoErrors();

    $student = User::query()->where('username', 'lab.student7')->sole();
    expect($student->email)->toBeNull()->and($student->phone)->toBeNull();

    $this->actingAs($student)->get('/competitions/brahin/register')->assertOk();
    $this->post('/competitions/brahin/register', [
        'full_name' => 'عبدالله محمد أحمد الغامدي', 'national_id' => studentNationalId(31), 'category_id' => CompetitionCategory::query()->where('name', 'المرحلة الثانوية')->value('id'),
        'grade' => 'الأول الثانوي', 'region_id' => CompetitionRegion::query()->active()->value('id'), 'gender' => 'male', 'pledge' => '1',
    ])->assertSessionHasNoErrors();

    $this->post('/competitions/brahin/rounds/'.$this->round->uuid.'/start')->assertRedirect();

    expect(CompetitionAttempt::query()->where('user_id', $student->id)->exists())->toBeTrue();
});

it('gives the technical admin the full national ID and the results operations, and keeps the super admin read-only', function () {
    studentQuestion($this->round);
    $technical = userWithRole(RoleName::TechnicalAdmin);
    $super = userWithRole(RoleName::SuperAdmin);
    $submission = submissionOf(registeredStudent($this->competition, 3));

    app(RevealStudentIdentity::class)->confirm($technical, $this->competition, 'password');

    expect($technical->checkPermissionTo('competition.export_national_id'))->toBeTrue()
        ->and($technical->checkPermissionTo('competition.manage_results'))->toBeTrue()
        ->and(app(RevealStudentIdentity::class)->handle($technical, $submission)['national_id'])->toBe(studentNationalId(3))
        ->and(fn () => app(RevealStudentIdentity::class)->handle($super, $submission))->toThrow(AuthorizationException::class);

    $this->travel(4)->hours();
    expect(app(PublishRoundResults::class)->handle($technical, $this->round)->resultsPublished())->toBeTrue();

    // والمشرف العام اطلاع على حفظ المسابقة نفسها: لا إنشاء ولا تعديل
    $data = ['title' => 'محاولة', 'slug' => 'super-try', 'opens_at' => now()->toDateTimeString(), 'closes_at' => now()->addWeek()->toDateTimeString(), 'student_mode' => true];
    expect(fn () => app(SaveCompetition::class)->handle($super, new Competition, $data))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SaveCompetition::class)->handle($super, $this->competition->fresh(), [...$data, 'slug' => 'brahin']))->toThrow(AuthorizationException::class);
});

it('reaches the competitions from the main menu and the account card', function () {
    $this->seed(MenuSeeder::class);
    $student = registeredStudent($this->competition);

    $this->get('/')->assertSee('href="'.url('/competitions').'"', false)->assertSee('المسابقات');
    $this->actingAs($student)->get(route('account.dashboard'))->assertSee(route('account.competitions'), false)->assertSee(__('competitions.account.nav'));
});

it('computes the category maximum check once and keeps it until the bank changes', function () {
    $middle = CompetitionCategory::query()->where('name', 'المرحلة المتوسطة')->value('id');
    $secondary = CompetitionCategory::query()->where('name', 'المرحلة الثانوية')->value('id');
    $this->competition->openCategories()->sync([$middle, $secondary]);
    studentQuestion($this->round, ['category_id' => $middle, 'points' => 1]);
    $other = studentQuestion($this->round, ['category_id' => $secondary, 'points' => 3]);
    $round = $this->round->fresh();

    expect(AttemptLayout::mixedMaxScores($round))->not->toBe([]);

    // تعديل مباشر بلا مسح للذاكرة لا يُرى، ومسحها مع كل تعديل في البنك (SaveStudentQuestion) يعيد الحساب
    $other->forceFill(['points' => 1])->save();
    expect(AttemptLayout::mixedMaxScores($round))->not->toBe([]);

    RoundQuestions::forget($round->id);
    expect(AttemptLayout::mixedMaxScores($round))->toBe([]);

    $this->actingAs($this->director);
    Livewire::test(RoundsRelationManager::class, ['ownerRecord' => $this->competition, 'pageClass' => EditCompetition::class])
        ->assertSee(__('competitions.student.bank_ready'));
});

it('keeps the ranking scope and prizes locked after the last branch is published, even when a branch is added', function () {
    studentQuestion($this->round);
    $this->travel(4)->hours();
    app(PublishRoundResults::class)->handle($this->director, $this->round);

    expect($this->competition->fresh()->ranking_locked_at)->not->toBeNull();

    $added = new CompetitionRound(['name' => 'فرع مضاف', 'sort_order' => 9, 'starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2), 'question_count' => 1, 'duration_minutes' => 30]);
    $added->competition_id = $this->competition->id;
    $added->save();
    $competition = $this->competition->fresh();

    expect($competition->cumulativeLocked())->toBeTrue()
        ->and(fn () => app(SaveCompetition::class)->handle($this->director, $competition, [
            'title' => $competition->title, 'slug' => 'brahin', 'opens_at' => $competition->opens_at, 'closes_at' => $competition->closes_at, 'ranking_scope' => 'category',
        ]))->toThrow(ValidationException::class);
});

// ── بعد دمج #67: خطوات النشر

it('refreshes the cached round banks after a deploy, so a migration that renames a category is seen at once', function () {
    studentQuestion($this->round);
    $before = RoundQuestions::key($this->round->id);

    $this->artisan('ejaz:refresh-content')->assertSuccessful();

    expect(RoundQuestions::key($this->round->id))->not->toBe($before);
});

it('backfills the ranking lock for competitions whose branches were all published before the column existed', function () {
    $migration = require database_path('migrations/2026_10_27_100300_backfill_competition_ranking_locked_at.php');
    $published = now()->subDay()->startOfSecond();
    $this->round->forceFill(['results_published_at' => $published])->save();
    $open = Competition::factory()->student()->create(['slug' => 'still-running']);

    $migration->up();

    expect($this->competition->fresh()->ranking_locked_at?->equalTo($published))->toBeTrue()
        ->and($open->fresh()->ranking_locked_at)->toBeNull();
});

it('backfills the ranking lock at the first cumulative draw even with a branch unpublished, and a second run changes nothing', function () {
    $migration = require database_path('migrations/2026_10_27_100300_backfill_competition_ranking_locked_at.php');
    $drawnAt = now()->subHours(5)->startOfSecond();
    // سحب تراكمي سابق للعمود (بيانات قديمة)
    $cumulativeDraw = function (StudentGender $gender, Carbon $at): void {
        $draw = new CompetitionDraw;
        $draw->competition_id = $this->competition->id;
        $draw->round_id = null;
        $draw->gender = $gender;
        $draw->places = 1;
        $draw->tie_score = 5;
        $draw->seed = str_repeat('a', 64);
        $draw->entrants = ['A1', 'B2'];
        $draw->entrants_hash = DrawSelector::fingerprint(['A1', 'B2']);
        $draw->entrants_count = 2;
        $draw->winners_count = 1;
        $draw->winners = ['A1'];
        $draw->executed_by = $this->director->id;
        $draw->executed_at = $at;
        $draw->save();
    };

    $cumulativeDraw(StudentGender::Male, $drawnAt);

    expect($this->round->fresh()->resultsPublished())->toBeFalse();

    $migration->up();
    $locked = $this->competition->fresh()->ranking_locked_at;

    expect($locked?->equalTo($drawnAt))->toBeTrue();

    // تشغيل ثانٍ لا يمس ما قُفل: ولو ظهر سحب أقدم (الجنس الآخر) لبقيت لحظة القفل كما سُجّلت أول مرة
    $cumulativeDraw(StudentGender::Female, now()->subHours(10)->startOfSecond());
    $this->round->forceFill(['results_published_at' => now()])->save();
    $migration->up();

    expect($this->competition->fresh()->ranking_locked_at?->equalTo($locked))->toBeTrue();
});
