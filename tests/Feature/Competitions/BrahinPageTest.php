<?php

use App\Actions\Competitions\SaveCompetition;
use App\Actions\Competitions\StartAttempt;
use App\Enums\CompetitionStatus;
use App\Enums\RoleName;
use App\Models\Competition;
use App\Models\CompetitionRound;
use App\Models\CompetitionSubmission;
use App\Models\User;
use App\Support\Dates;
use Database\Seeders\BrahinCompetitionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/*
| صفحة مسابقة «البراهين العلمية» بملاحظات المدير التنفيذي (29 أكتوبر 2026، fix/brahin-director-notes): المجالات من مجال كل
| مرحلة، وبطاقات المراحل بلا عدد ولا مدة ولا تاريخ إلا بخيار «إظهار مواعيد المراحل»، والعدد والمدة في صفحة البدء،
| والأقسام النصية بطاقات مطوية، ودليل الدخول شرائح، وزر التسجيل مكوّن واحد بحالاته.
*/

beforeEach(function () {
    config(['competitions.exam.national_id_key' => 'test-hmac-key', 'competitions.student.min_questions' => 1]);

    (new BrahinCompetitionSeeder)->run();
    $this->competition = Competition::query()->where('slug', 'brahin-1448')->sole();
    $this->competition->forceFill(['is_published' => true])->save();
    $this->rounds = $this->competition->rounds()->get();
});

it('shows the four domains and the stage cards with the day and date, without the domain, the question count or the duration', function () {
    $round = $this->rounds->first();
    $day = __('competitions.student.page.round_day', ['day' => __('dates.days.'.Dates::local($round->starts_at)->dayOfWeek), 'date' => Dates::display($round->starts_at), 'hijri' => Dates::hijri($round->starts_at)]);
    // البذرة تبدأ المراحل الخميس 00:00 بتوقيت الرياض (الأربعاء بتوقيت UTC): يُقرأ اليوم بتوقيت الرياض
    expect($day)->toStartWith('الخميس، ');

    $response = $this->get('/competitions/brahin-1448')->assertOk()
        ->assertSeeInOrder([
            __('competitions.student.page.partner'), 'وزارة التعليم',
            __('competitions.student.page.domains'), 'المجال الأول', 'الحقائق العلمية في الأنفس', 'المجال الرابع', 'الحقائق العلمية في علوم البحار',
            __('competitions.student.rounds'), 'تمر المسابقة بأربع مراحل متتابعة، وتُعقد كل مرحلة يوم الخميس بحسب الجدول الزمني والتواريخ المعتمدة لها.',
            'المرحلة الأولى', $day,
            __('competitions.student.page.prizes'),
            __('competitions.student.page.intro'), __('competitions.student.page.rules'), __('competitions.student.page.guide_problems'),
            __('competitions.student.page.guide_steps'),
        ])
        ->assertSee(__('competitions.student.upcoming'))
        ->assertDontSee('المجال الأول: الحقائق العلمية في الأنفس')
        ->assertDontSee('data-round-dates', false)
        ->assertDontSee(trans_choice('competitions.student.questions_count', $round->question_count, ['count' => $round->question_count]))
        ->assertDontSee(trans_choice('competitions.student.minutes', $round->duration_minutes, ['count' => $round->duration_minutes]))
        ->assertDontSee(Dates::display($round->starts_at, withTime: true));

    // الشرط باقٍ في الضوابط لا في مقدمة المراحل
    $html = $response->getContent();
    expect(strpos($html, __('competitions.student.page.all_branches')))->toBeGreaterThan(strpos($html, __('competitions.student.page.rules')));
});

it('shows the stage dates only with the "show stage dates" switch', function () {
    $round = $this->rounds->first();
    $this->competition->forceFill(['show_round_dates' => true])->save();

    $this->get('/competitions/brahin-1448')
        ->assertSee('data-round-dates', false)
        ->assertSee(Dates::display($round->starts_at, withTime: true))
        ->assertSee(Dates::display($round->ends_at, withTime: true));
});

it('saves the switch from the panel action, off by default, and rejects a non-boolean', function () {
    $director = userWithRole(RoleName::ExecutiveDirector);
    $data = fn (mixed $value): array => [...$this->competition->only(['title', 'slug', 'opens_at', 'closes_at']), 'student_mode' => true, 'show_round_dates' => $value];

    expect(Competition::factory()->student()->create()->fresh()->show_round_dates)->toBeFalse();

    app(SaveCompetition::class)->handle($director, $this->competition, $data(true));
    expect($this->competition->fresh()->show_round_dates)->toBeTrue();

    expect(fn () => app(SaveCompetition::class)->handle($director, $this->competition->fresh(), $data('yes')))->toThrow(ValidationException::class);
});

it('opens and closes the stage windows from the stored dates while they are hidden', function () {
    $round = $this->competition->rounds()->firstOrFail();
    studentQuestion($round);
    $student = registeredStudent($this->competition);

    expect($this->competition->show_round_dates)->toBeFalse()
        ->and(fn () => app(StartAttempt::class)->handle($student, $round))->toThrow(AuthorizationException::class, __('competitions.student.round_closed'));

    $this->travelTo($round->starts_at->copy()->addMinute());
    expect(app(StartAttempt::class)->handle($student, $round->fresh())->round_id)->toBe($round->id);

    $this->actingAs($student)->get('/competitions/brahin-1448')->assertSee(__('competitions.student.resume'));
});

it('shows the question count and the duration on the exam start page', function () {
    $round = $this->rounds->first();
    $round->forceFill(['question_count' => 25, 'duration_minutes' => 40])->save();
    $student = registeredStudent($this->competition);

    $this->actingAs($student)->get('/competitions/brahin-1448/rounds/'.$round->uuid)->assertOk()
        ->assertSeeInOrder([__('competitions.student.questions_and_duration'), '25 سؤالاً', '40 دقيقة']);
});

it('renders the text sections as collapsible cards in the page, closed, with their content for search engines', function () {
    $html = $this->get('/competitions/brahin-1448')->getContent();

    expect(substr_count($html, '<details'))->toBe(13)
        ->and($html)->not->toContain('<details open')
        ->and($html)->toContain('data-collapsible-all')
        ->toContain('aria-expanded="false"')
        ->toContain('بناء جيل محب للعلم')
        ->toContain('المرحلة الجامعية');
});

it('renders the entry guide as one slide per step, with the controls hidden until the script runs', function () {
    $steps = $this->competition->pageList('guide_steps');
    $html = $this->get('/competitions/brahin-1448')->getContent();

    expect(substr_count($html, 'data-slide="'))->toBe(count($steps))
        ->and(substr_count($html, 'data-slide-dot="'))->toBe(count($steps))
        // وصف الدوّار بالعربية كدوّارات البوابة (x-carousel — الدفعة الثانية من التحسين البصري)
        ->and($html)->toContain('aria-roledescription="'.__('ui.slider.carousel').'"')
        ->toContain('data-slides-controls hidden')
        ->toContain(__('competitions.student.page.slide_step', ['number' => 1, 'total' => count($steps)]))
        ->toContain($steps[0]);

    $this->get('/competitions/brahin-1448/guide')->assertOk()->assertSee('data-slides', false);
});

it('applies the director\'s stages intro to the stored page without touching an edited one', function () {
    $migration = require database_path('migrations/2026_10_30_100100_add_brahin_rounds_intro.php');
    $content = $this->competition->page_content;
    unset($content['rounds_intro']);
    DB::table('competitions')->where('id', $this->competition->id)->update(['page_content' => json_encode($content, JSON_UNESCAPED_UNICODE)]);

    $migration->up();
    expect($this->competition->fresh()->pageText('rounds_intro'))->toStartWith('تمر المسابقة بأربع مراحل متتابعة');

    $this->competition->forceFill(['page_content' => [...$content, 'rounds_intro' => 'نص المحرر']])->save();
    $migration->up();
    expect($this->competition->fresh()->pageText('rounds_intro'))->toBe('نص المحرر');
});

describe('the registration button component', function () {
    $render = fn (Competition $competition, ?CompetitionSubmission $submission = null): string => Blade::render(
        '<x-competitions.student-cta :competition="$competition" :submission="$submission" :status="$status" />',
        ['competition' => $competition, 'submission' => $submission, 'status' => $competition->status()],
    );

    it('sends a guest to the registration page through the login, with a create-account button', function () use ($render) {
        expect($this->competition->status())->toBe(CompetitionStatus::Open);

        expect($render($this->competition))
            ->toContain(__('competitions.student.login_to_register'))
            ->toContain(route('competitions.student.register', $this->competition->slug))
            // وإنشاء الحساب إلى صفحة التسجيل نفسها بـ signup=1، فيعود إليها بعد إنشائه (#100)
            ->toContain(e(route('competitions.student.register', ['slug' => $this->competition->slug, 'signup' => 1])));
    });

    it('offers registration to a signed-in visitor', function () use ($render) {
        $this->actingAs(User::factory()->create());

        expect($render($this->competition))
            ->toContain(__('competitions.student.register_cta'))
            ->not->toContain(__('competitions.student.login_to_register'));
    });

    it('shows the reference to a registered student', function () use ($render) {
        $student = registeredStudent($this->competition);
        $this->actingAs($student);
        $submission = CompetitionSubmission::query()->where('user_id', $student->id)->sole();

        expect($render($this->competition, $submission))
            ->toContain(__('competitions.student.registered_as', ['reference' => $submission->reference]))
            ->not->toContain(route('competitions.student.register', $this->competition->slug));
    });

    it('shows the closed and upcoming states without a button', function () use ($render) {
        $this->competition->forceFill(['closes_at' => now()->subMinute(), 'opens_at' => now()->subWeek()])->save();
        expect($render($this->competition->fresh()))->toContain(__('competitions.student.registration_closed'))->not->toContain('<a ');

        $this->competition->forceFill(['opens_at' => now()->addDay(), 'closes_at' => now()->addWeek()])->save();
        expect($render($this->competition->fresh()))->toContain(Dates::display($this->competition->fresh()->opens_at, withTime: true))->not->toContain('<a ');
    });

    it('is the one the side column uses', function () {
        $this->get('/competitions/brahin-1448')->assertSee('data-student-cta', false)->assertSee(__('competitions.student.login_to_register'));
    });
});

it('keeps the registered student\'s round button and status in the stage card', function () {
    /** @var CompetitionRound $round */
    $round = $this->rounds->first();
    $round->forceFill(['starts_at' => now()->subHour(), 'ends_at' => now()->addHour()])->save();

    $this->get('/competitions/brahin-1448')->assertSee(__('competitions.student.running'))->assertDontSee(__('competitions.student.enter'));

    $this->actingAs(registeredStudent($this->competition))->get('/competitions/brahin-1448')->assertSee(__('competitions.student.enter'));
});
