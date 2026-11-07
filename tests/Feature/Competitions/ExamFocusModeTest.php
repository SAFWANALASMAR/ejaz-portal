<?php

use App\Models\Competition;
use App\Models\CompetitionAttempt;
use App\Models\User;
use App\Support\SpaLink;

/*
| وضع التركيز في صفحات اختبار الطالب (decisions/2026-11-06-exam-focus-mode.md): من أول سؤال حتى التسليم بلا رأس الموقع وقائمته،
| ولا شريط «حسابي» الجانبي، ولا شريط العودة ولا التذييل ولا المساعد ولا البحث ولا طبقة الانتقال ولا Livewire. وشريط علوي وحده
| بالشعار واسم المرحلة والمسابقة والوقت الباقي، وأرقام الصفحات عمود بحالاتها. وصفحات المسابقة الأخرى بقالب الموقع كما هي.
*/

beforeEach(function () {
    config(['competitions.exam.national_id_key' => 'test-hmac-key']);
    // علم Livewire لحقن أصوله ثابت في العملية (SupportAutoInjectedAssets)، يرفعه اختبار سابق بصفحة فيها @persist، ولا يُفرَّغ بين
    // الاختبارات. وفي الطلب الحقيقي يبدأ مطفأً، فيُفرَّغ هنا ليُختبر غياب Livewire عن صفحة الاختبار نفسها
    Livewire\Livewire::flushState();

    $this->competition = Competition::factory()->student()->create(['slug' => 'students', 'title' => 'مسابقة تجريبية للتركيز']);
    $this->round = $this->competition->rounds()->firstOrFail();
    $this->round->forceFill(['name' => 'المرحلة التجريبية للتركيز'])->save();
});

function focusUrl(string $path = ''): string
{
    return '/competitions/students/rounds/'.test()->round->uuid.$path;
}

/**
 * طالب مسجّل بدأ الاختبار في مرحلة بسؤالين (صفحتين).
 */
function focusStudent(): User
{
    studentQuestion(test()->round, ['prompt' => 'السؤال الأول للتركيز']);
    studentQuestion(test()->round, ['prompt' => 'السؤال الثاني للتركيز']);
    $student = registeredStudent(test()->competition);
    test()->actingAs($student)->post(focusUrl('/start'))->assertRedirect(focusUrl('/units/1'));

    return $student;
}

/**
 * ما لا يظهر وقت الاختبار: رأس الموقع وقائمته، وشريط «حسابي» الجانبي، والعودة، والتذييل، والمساعد، والبحث، وطبقة الانتقال، وLivewire.
 */
function assertNoSiteChrome(string $html): void
{
    foreach (['data-site-header', 'nav-capsules', 'account-nav', 'data-account', 'data-back-nav', 'data-site-footer', 'data-assistant', 'data-search-dialog', 'data-page-transition', 'data-update-uri', 'wire:navigate'] as $marker) {
        expect($html)->not->toContain($marker);
    }
}

it('shows the question page with the exam bar alone: no site header, menu, account sidebar, footer or assistant', function () {
    focusStudent();

    $html = $this->get(focusUrl('/units/1'))->assertOk()->getContent();

    assertNoSiteChrome($html);
    expect($html)
        ->toContain('data-exam-bar')
        ->toContain('<h1 class="exam-bar-round">المرحلة التجريبية للتركيز</h1>')
        ->toContain('مسابقة تجريبية للتركيز')
        ->toContain('images/brand/logo-mark.png')
        ->toContain('data-exam-timer=')
        ->toContain('role="timer"')
        ->toContain(__('competitions.student.time_left'))
        ->toContain('exam-timer-tile')
        ->toContain('data-exam-units')
        ->toContain('data-message="'.__('competitions.student.saving').'"');
});

it('shows the page numbers as a column with the current, answered and unanswered states, after the question in the code', function () {
    focusStudent();
    $attempt = CompetitionAttempt::query()->sole();
    $first = (int) $attempt->layout[0]['questions'][0]['id'];

    $this->post(focusUrl('/units/1'), ['answers' => [$first => $attempt->optionToken($first, 'b')], 'go' => 'next'])->assertRedirect(focusUrl('/units/2'));
    $html = $this->get(focusUrl('/units/2'))->assertOk()->getContent();

    $nav = str($html)->after('data-exam-units')->before('</nav>');
    expect((string) $nav)
        ->toMatch('/value="1"[^>]*aria-label="'.preg_quote(__('competitions.student.go_to_unit', ['number' => 1, 'status' => __('competitions.exam.answered')]), '/').'"[^>]*class="[^"]*bg-primary text-white/')
        ->toMatch('/value="2"[^>]*aria-current="step"[^>]*class="[^"]*border border-line bg-white[^"]*ring-2 ring-accent/')
        ->toContain('value="review"');

    // «التالي» أول زر إرسال في النموذج (زر Enter)، والعمود بعده في الشيفرة
    expect(strpos($html, 'value="next"'))->toBeLessThan(strpos($html, 'data-exam-units'));
});

it('shows the review page in focus mode too, with full page links back to the questions', function () {
    focusStudent();

    $html = $this->get(focusUrl('/review'))->assertOk()->getContent();

    assertNoSiteChrome($html);
    expect($html)->toContain('data-exam-bar')->toContain('data-exam-timer=')->toContain('href="'.url(focusUrl('/units/1')).'"');
});

it('loads the exam pages fully, without Livewire navigation, and keeps the site layout on the other competition pages', function () {
    focusStudent();

    expect(SpaLink::allows(focusUrl('/units/1')))->toBeFalse()
        ->and(SpaLink::allows(focusUrl('/review')))->toBeFalse()
        ->and(SpaLink::allows(focusUrl('/status')))->toBeTrue();

    $this->get(focusUrl('/status'))->assertOk()->assertSee('data-site-header', false)->assertSee('data-site-footer', false)->assertDontSee('data-exam-bar', false);
    $this->get('/competitions/students')->assertOk()->assertSee('data-site-header', false)->assertDontSee('data-exam-bar', false);
});

it('keeps the exam pages for the registered student alone', function () {
    focusStudent();

    $this->post('/logout');
    $this->get(focusUrl('/units/1'))->assertRedirect('/login');

    $this->actingAs(User::factory()->create())->get(focusUrl('/units/1'))->assertNotFound();
    $this->get(focusUrl('/review'))->assertNotFound();
});

it('colours a page the student visited and left unanswered amber, the answered blue, and what they have not reached white, with a legend', function () {
    foreach (range(1, 4) as $i) {
        studentQuestion($this->round);
    }
    $student = registeredStudent($this->competition);
    $this->actingAs($student)->post(focusUrl('/start'))->assertRedirect(focusUrl('/units/1'));
    $attempt = CompetitionAttempt::query()->sole();
    $second = (int) $attempt->layout[1]['questions'][0]['id'];

    // الأولى: زارها وانتقل عنها بلا إجابة، والثانية: أجاب عنها، والثالثة: الحالية، والرابعة: لم يصلها
    $this->get(focusUrl('/units/1'))->assertOk();
    $this->post(focusUrl('/units/1'), ['go' => 'next'])->assertRedirect(focusUrl('/units/2'));
    $this->get(focusUrl('/units/2'))->assertOk();
    $this->post(focusUrl('/units/2'), ['answers' => [$second => $attempt->optionToken($second, 'b')], 'go' => 'next'])->assertRedirect(focusUrl('/units/3'));
    $nav = (string) str($this->get(focusUrl('/units/3'))->assertOk()->getContent())->after('data-exam-units')->before('</nav>');

    $state = fn (int $number, string $status): string => '/value="'.$number.'" data-exam-step data-unit-state="'.$status.'"\s+aria-label="'.preg_quote(__('competitions.student.go_to_unit', ['number' => $number, 'status' => __('competitions.student.unit_states.'.$status)]), '/').'"/';

    expect($nav)
        ->toMatch($state(1, 'skipped'))
        ->toMatch('/value="1"[^>]*class="[^"]*exam-unit-skipped/')
        ->toMatch($state(2, 'answered'))
        ->toMatch('/value="2"[^>]*class="[^"]*bg-primary text-white/')
        ->toMatch('/value="3" data-exam-step data-unit-state="unvisited"\s+aria-label="'.preg_quote(__('competitions.student.go_to_unit', ['number' => 3, 'status' => __('competitions.exam.unanswered')]), '/').'"\s+aria-current="step"/')
        ->toMatch($state(4, 'unvisited'))
        ->toMatch('/value="4"[^>]*class="[^"]*border border-line bg-white/')
        ->toContain('data-exam-units-legend')
        ->toContain(__('competitions.student.units_legend.answered'))
        ->toContain(__('competitions.student.units_legend.skipped'))
        ->toContain(__('competitions.student.units_legend.current'));

    // ولا كهرماني لصفحة لم يزرها، ولا للحالية ولو زارها من قبل
    expect(substr_count($nav, 'data-unit-state="skipped"'))->toBe(1);
    $this->post(focusUrl('/units/3'), ['go' => '1'])->assertRedirect(focusUrl('/units/1'));
    $back = (string) str($this->get(focusUrl('/units/1'))->getContent())->after('data-exam-units')->before('</nav>');
    expect($back)->toMatch('/value="1" data-exam-step data-unit-state="unvisited"[^>]*aria-current="step"/')
        ->toMatch($state(3, 'skipped'))
        ->toMatch($state(4, 'unvisited'));
});

// قائمة «زاره» لا تبقى في الجلسة بعد التسليم (مراجعة #211)
it('forgets the visited pages from the session once the attempt is submitted', function () {
    focusStudent();
    $attempt = CompetitionAttempt::query()->sole();
    $key = 'competitions.exam.visited.'.$attempt->id;

    $this->get(focusUrl('/units/1'))->assertOk();
    expect(session($key))->toBe([1]);

    $this->post(focusUrl('/submit'))->assertRedirect();
    expect(session()->has($key))->toBeFalse();
});

/*
| ملء الشاشة والتنقّل داخل الصفحة (ملاحظات المدير التنفيذي، 7 نوفمبر 2026 — decisions/2026-11-07-exam-fullscreen.md)
*/

it('puts a hidden fullscreen button with its two labels and icons in the exam bar, and marks the review links for in-page navigation', function () {
    focusStudent();

    $bar = (string) str($this->get(focusUrl('/units/1'))->assertOk()->getContent())->after('data-exam-bar')->before('exam-body');

    expect($bar)->toMatch('/<button type="button" class="exam-fullscreen" data-exam-fullscreen hidden aria-pressed="false"/')
        ->toContain('data-enter="'.__('competitions.student.fullscreen_enter').'"')
        ->toContain('data-exit="'.__('competitions.student.fullscreen_exit').'"')
        ->toContain('exam-fullscreen-enter')
        ->toContain('exam-fullscreen-exit')
        ->toContain('data-exam-timer');

    $this->get(focusUrl('/review'))->assertOk()->assertSee('data-exam-fullscreen', false)->assertSee('data-exam-link', false);
});

it('keeps the plain redirects for the exam form without the in-page header', function () {
    focusStudent();

    $this->post(focusUrl('/units/1'), ['go' => 'next'])->assertRedirect(focusUrl('/units/2'));
    $this->post(focusUrl('/units/2'), ['go' => 'submit'])->assertRedirect(focusUrl());
});

it('moves between pages in place by the same redirect, and leaves the exam by an address the page opens in full', function () {
    focusStudent();
    $inline = ['X-Exam-Inline' => '1'];

    // بين الصفحات: التحويل نفسه، يتبعه السكربت ويضع منطقة الاختبار مكان الحالية
    $this->withHeaders($inline)->post(focusUrl('/units/1'), ['go' => 'next'])->assertRedirect(focusUrl('/units/2'));
    $this->withHeaders($inline)->post(focusUrl('/units/2'), ['go' => 'review'])->assertRedirect(focusUrl('/review'));

    // والتسليم: بلا تحويل، فالعنوان في رأس للسكربت
    $this->withHeaders($inline)->post(focusUrl('/units/2'), ['go' => 'submit'])
        ->assertNoContent()
        ->assertHeader('X-Exam-Location', url(focusUrl()));
    expect(CompetitionAttempt::query()->sole()->isInProgress())->toBeFalse();
});

it('keeps the time up message for the full page load after an in-page save', function () {
    focusStudent();
    $this->travel(31)->minutes();

    $this->withHeaders(['X-Exam-Inline' => '1'])->post(focusUrl('/units/1'), ['go' => 'next'])
        ->assertNoContent()
        ->assertHeader('X-Exam-Location', url(focusUrl()));

    $this->get(focusUrl())->assertSee(__('competitions.student.time_up'));
});
