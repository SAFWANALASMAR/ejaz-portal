<?php

use App\Models\User;

// رأس الصفحة الداخلية في لوحة المستفيد (الدفعة ٤ من طلب لوحة الإدارة، 4 نوفمبر 2026): أيقونة القسم ووصفه من config/account.php

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('heads each inner account page with its section icon and description', function (string $route, string $title, string $description) {
    $this->actingAs($this->user)->get(route($route))
        ->assertOk()
        ->assertSeeInOrder(['data-account-head', 'data-icon-tile', __($title), __($description)], false);
})->with([
    'requests' => ['account.requests.index', 'services.requests.title', 'services.nav.requests_description'],
    'programs' => ['account.programs.index', 'programs.mine.title', 'programs.nav.programs_description'],
    'profile' => ['account.profile', 'account.profile.title', 'account.nav.profile_description'],
    'competitions' => ['account.competitions', 'competitions.account.title', 'competitions.account.nav_description'],
]);

it('keeps the dashboard on its welcome banner without the inner page head', function () {
    $this->actingAs($this->user)->get(route('account.dashboard'))
        ->assertOk()
        ->assertDontSee('data-account-head', false);
});

it('heads a section without a description with its title and icon only', function () {
    $this->actingAs($this->user)->get(route('account.my-reading'))
        ->assertOk()
        ->assertSee('data-account-head', false)
        ->assertDontSee('account-head-text', false);
});
