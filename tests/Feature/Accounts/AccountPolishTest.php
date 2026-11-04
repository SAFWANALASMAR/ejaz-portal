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

it('shows the dashboard cards with a bold count and the gradient arrow', function () {
    ProgramRegistration::factory()->for($this->user)->for(Program::factory()->create(['starts_at' => now()->addDays(4)]))->create();

    $this->actingAs($this->user)->get(route('account.dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['data-account-cards', 'acct-number', 'acct-arrow'], false);
});
