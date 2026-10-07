<?php

use App\Enums\RoleName;
use App\Http\Middleware\AdminIdleTimeout;
use App\Models\User;

it('sends guests to the unified login page from every admin screen', function (string $path) {
    $this->get($path)->assertRedirect(route('login'));
})->with(['/admin', '/admin/menu-items', '/admin/menu-items/create', '/admin/users', '/admin/activities', '/admin/site-settings']);

it('forbids accounts without an administrative role', function () {
    $this->actingAs(userWithRole(RoleName::Member))->get('/admin')->assertForbidden();
    $this->actingAs(userWithRole(RoleName::AssemblyMember))->get('/admin')->assertForbidden();
});

it('lets the super admin with two-factor in', function () {
    $this->actingAs(userWithRole(RoleName::SuperAdmin))->get('/admin')->assertOk();
});

it('lets every administrative role in', function (RoleName $role) {
    $this->actingAs(userWithRole($role))->get('/admin')->assertOk();
})->with(RoleName::panelRoles());

it('requires two-factor before sensitive roles reach the panel', function (RoleName $role) {
    $user = User::factory()->create();
    $user->assignRole($role->value);

    $this->actingAs($user)->get('/admin')->assertRedirect(route('account.security'));
})->with(RoleName::twoFactorRoles());

it('requires two-factor for the reviewer, who sees applicants videos and answers', function () {
    expect(RoleName::twoFactorRoles())->toContain(RoleName::Reviewer);
});

it('blocks deactivated administrators and ends their session', function () {
    $admin = userWithRole(RoleName::SuperAdmin);
    $admin->forceFill(['is_active' => false])->save();

    // اللوحة ترفض فوراً، وأول طلب للموقع ينهي الجلسة
    $this->actingAs($admin)->get('/admin')->assertForbidden();
    $this->get('/account')->assertRedirect(route('login'));
    $this->assertGuest();
});

it('logs administrators out after the idle limit', function () {
    $admin = userWithRole(RoleName::SuperAdmin);

    $this->actingAs($admin)
        ->withSession([AdminIdleTimeout::SESSION_KEY => now()->subMinutes(31)->getTimestamp()])
        ->get('/admin')
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('keeps active administrators signed in', function () {
    $admin = userWithRole(RoleName::SuperAdmin);

    $this->actingAs($admin)
        ->withSession([AdminIdleTimeout::SESSION_KEY => now()->subMinutes(5)->getTimestamp()])
        ->get('/admin')
        ->assertOk();
});
