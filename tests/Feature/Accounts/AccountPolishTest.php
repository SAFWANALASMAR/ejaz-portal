<?php

use App\Models\Opening;
use App\Models\Program;
use App\Models\ProgramRegistration;
use App\Models\ServiceRequest;
use App\Models\User;

/*
| حساب المستفيد، الجزء الثاني من الدفعة ٤ (طلب لوحة الإدارة، 4 نوفمبر 2026): الترحيب «فاتح بصورة واحدة» باسم المستخدم، والبطاقات
| الفاتحة بمربعات أيقونات، والحالات الفارغة المرسومة، والصفوف بأيقوناتها. والأنماط في resources/css/account-polish.css بنطاق [data-account].
*/

beforeEach(function () {
    $this->user = User::factory()->create(['name' => 'سارة المستفيدة']);
});

it('greets the user by name on a light welcome with one photo, not the dark image hero', function () {
    $this->actingAs($this->user)->get(route('account.dashboard'))
        ->assertOk()
        ->assertSee('data-account', false)
        ->assertSeeInOrder(['data-account-welcome', __('account.dashboard.welcome', ['name' => 'سارة المستفيدة']), 'acct-welcome-photo'], false)
        ->assertDontSee('concept-hero-scrim', false);
});

it('scopes every account page so its styles never reach the public site', function (string $route) {
    $this->actingAs($this->user)->get(route($route))
        ->assertOk()
        ->assertSee('data-account', false)
        ->assertSee('data-account-main', false);
})->with(['account.dashboard', 'account.requests.index', 'account.programs.index', 'account.profile', 'account.my-reading']);

it('keeps the account scope off public pages', function () {
    $this->get('/')->assertOk()->assertDontSee('data-account-main', false);
});

it('draws the empty upcoming and requests panels on a new account', function () {
    $this->actingAs($this->user)->get(route('account.dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['data-account-upcoming', 'data-empty-state', __('account.summary.upcoming_empty')], false)
        ->assertSeeInOrder(['account-latest-requests', 'data-empty-state', __('account.summary.requests_empty')], false);
});

it('lists my requests and programmes as rows with an icon tile and the status pill', function () {
    ServiceRequest::factory()->for($this->user)->for(Opening::factory()->create(['title' => 'طلب الصف']))->create();
    ProgramRegistration::factory()->for($this->user)->for(Program::factory()->create(['title' => 'برنامج الصف', 'starts_at' => now()->addDays(3)]))->create();

    $this->actingAs($this->user)->get(route('account.requests.index'))
        ->assertOk()
        ->assertSeeInOrder(['acct-row', 'data-icon-tile', 'طلب الصف', 'rounded-full'], false);

    $this->actingAs($this->user)->get(route('account.programs.index'))
        ->assertOk()
        ->assertSeeInOrder(['acct-row', 'data-icon-tile', 'برنامج الصف'], false);
});

it('shows the dashboard cards with the count in a pill and the gradient arrow', function () {
    ProgramRegistration::factory()->for($this->user)->for(Program::factory()->create(['starts_at' => now()->addDays(4)]))->create();

    $this->actingAs($this->user)->get(route('account.dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['data-account-cards', 'acct-count', 'acct-arrow'], false);
});

/** زر «تصفّح…» داخل صندوق الحالة الفارغة تحت رسالتها مباشرة، لا بعد إغلاقه (ملاحظة المقيّم على #167) */
function expectBrowseInsideEmptyState(string $html, string $message, string $href, string $label): void
{
    expect($html)->toMatch('#'.preg_quote(e($message), '#').'</p>\s*<a href="'.preg_quote($href, '#').'"[^>]*>\s*'.preg_quote(e($label), '#').'#u');
}

it('puts a start button inside each empty panel of the dashboard and hides «view all» while empty', function () {
    $html = $this->actingAs($this->user)->get(route('account.dashboard'))->assertOk()->getContent();

    expectBrowseInsideEmptyState($html, __('account.summary.upcoming_empty'), route('programs.index'), __('programs.mine.browse'));
    expectBrowseInsideEmptyState($html, __('account.summary.requests_empty'), route('services.index'), __('services.requests.browse'));

    expect($html)
        ->not->toContain('<span class="sr-only">: '.__('account.summary.upcoming').'</span>')
        ->not->toContain('<span class="sr-only">: '.__('account.summary.latest_requests').'</span>');
});

it('shows «view all» on the dashboard panels once they have something', function () {
    ServiceRequest::factory()->for($this->user)->for(Opening::factory()->create())->create();
    ProgramRegistration::factory()->for($this->user)->for(Program::factory()->create(['starts_at' => now()->addDays(2)]))->create();

    $this->actingAs($this->user)->get(route('account.dashboard'))
        ->assertOk()
        ->assertSee(__('account.summary.view_all').'<span class="sr-only">: '.__('account.summary.upcoming').'</span>', false)
        ->assertSee(__('account.summary.view_all').'<span class="sr-only">: '.__('account.summary.latest_requests').'</span>', false)
        ->assertDontSee(__('account.summary.upcoming_empty'));
});

it('puts the browse button inside the empty box of my requests, programmes and competitions', function (string $page, string $message, string $target, string $label) {
    $html = $this->actingAs($this->user)->get(route($page))->assertOk()->getContent();

    expectBrowseInsideEmptyState($html, __($message), route($target), __($label));
})->with([
    'requests' => ['account.requests.index', 'services.requests.empty', 'services.index', 'services.requests.browse'],
    'programmes' => ['account.programs.index', 'programs.mine.empty', 'programs.index', 'programs.mine.browse'],
    'competitions' => ['account.competitions', 'competitions.account.empty', 'competitions.index', 'competitions.account.browse'],
]);

it('labels every «view all» on the account home the same', function () {
    $this->actingAs($this->user)->get(route('account.dashboard'))
        ->assertOk()
        ->assertSee(__('account.summary.view_all').'<span class="sr-only">: '.__('reading.title').'</span>', false);
});

it('heads every section of the form pages with an icon tile, keeping the anchors', function (string $route, array $titles) {
    $this->actingAs($this->user);
    if ($route === 'account.security') {
        session()->put('auth.password_confirmed_at', time());
    }

    $response = $this->get(route($route))->assertOk();

    foreach ($titles as $title) {
        $response->assertSeeInOrder(['data-account-section-title', 'data-icon-tile', __($title)], false);
    }
})->with([
    'profile' => ['account.profile', ['account.profile_summary.title', 'account.profile.info', 'reading.tracking.title', 'account.profile.password']],
    'security' => ['account.security', ['account.security.two_factor', 'account.security.other_sessions.title']],
    'data' => ['account.data', ['account.data.export', 'account.data.deletion']],
]);

it('keeps the profile anchors that other pages link to', function () {
    $this->actingAs($this->user)->get(route('account.profile'))
        ->assertOk()
        ->assertSee('id="password"', false)
        ->assertSee('id="reading-tracking"', false);
});
