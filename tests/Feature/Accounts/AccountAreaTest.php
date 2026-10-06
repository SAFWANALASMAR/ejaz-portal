<?php

use App\Enums\RoleName;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->user = User::factory()->create([
        'name' => 'سارة أحمد',
        'email' => 'sara@example.test',
        'phone' => '+966512345678',
        'phone_verified_at' => now(),
    ]);
});

it('keeps the account area for signed-in users', function (string $path) {
    $this->get($path)->assertRedirect(route('login'));
})->with(['/account', '/account/profile', '/account/security', '/account/data']);

it('signs out a user deactivated during an open session', function () {
    $this->actingAs($this->user)->get('/account')->assertOk();

    $this->user->forceFill(['is_active' => false])->save();

    $this->get('/account')
        ->assertRedirect(route('login'))
        ->assertSessionHas('error', __('auth.deactivated'));

    $this->assertGuest();
});

it('shows the dashboard with verification reminders', function () {
    $this->user->forceFill(['email_verified_at' => null, 'phone_verified_at' => null])->save();

    $this->actingAs($this->user)->get('/account')
        ->assertOk()
        ->assertSee('سارة أحمد')
        ->assertSee(__('account.dashboard.verify_email'))
        ->assertSee(__('account.dashboard.verify_phone'));
});

it('updates the profile and resets verification of a changed email or phone', function () {
    Notification::fake();

    $this->actingAs($this->user)->put('/user/profile-information', [
        'name' => 'سارة أحمد علي',
        'email' => 'sara.new@example.test',
        'phone' => '0598765432',
    ])->assertSessionHasNoErrors();

    $user = $this->user->fresh();

    expect($user->name)->toBe('سارة أحمد علي')
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->phone)->toBe('+966598765432')
        ->and($user->phone_verified_at)->toBeNull();

    Notification::assertSentTo($user, VerifyEmailNotification::class);
});

it('keeps verification when the contact details do not change', function () {
    $this->actingAs($this->user)->put('/user/profile-information', [
        'name' => 'سارة',
        'email' => 'sara@example.test',
        'phone' => '051 234 5678',
    ])->assertSessionHasNoErrors();

    expect($this->user->fresh()->hasVerifiedPhone())->toBeTrue()
        ->and($this->user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('changes the password only with the current one', function () {
    $this->actingAs($this->user)->put('/user/password', [
        'current_password' => 'wrong-current',
        'password' => 'brand-new-pass-2026',
        'password_confirmation' => 'brand-new-pass-2026',
    ])->assertSessionHasErrorsIn('updatePassword', 'current_password');

    $this->actingAs($this->user)->put('/user/password', [
        'current_password' => 'password',
        'password' => 'brand-new-pass-2026',
        'password_confirmation' => 'brand-new-pass-2026',
    ])->assertSessionHasNoErrors();

    expect(Hash::check('brand-new-pass-2026', $this->user->fresh()->password))->toBeTrue();
});

it('asks for the password before opening the security page', function () {
    $this->actingAs($this->user)->get('/account/security')->assertRedirect(route('password.confirm'));

    $this->actingAs($this->user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get('/account/security')
        ->assertOk()
        ->assertSee(__('account.security.enable'));
});

it('does not let administrators switch two-factor off', function () {
    $admin = userWithRole(RoleName::Secretary);

    $this->actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete('/user/two-factor-authentication')
        ->assertForbidden();

    expect($admin->fresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();
});

it('lets a beneficiary start two-factor setup', function () {
    $this->actingAs($this->user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post('/user/two-factor-authentication')
        ->assertRedirect();

    expect($this->user->fresh()->two_factor_secret)->not->toBeNull();
});

it('exports the user data after password confirmation without secrets', function () {
    $this->actingAs($this->user)->get('/account/data/export')->assertRedirect(route('password.confirm'));

    $response = $this->actingAs($this->user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get('/account/data/export')
        ->assertOk()
        ->assertHeader('Content-Disposition');

    expect($response->json('account.email'))->toBe('sara@example.test')
        ->and($response->getContent())->not->toContain('password')
        ->not->toContain('two_factor_secret');
});

it('records a deletion request only with the current password', function () {
    $this->actingAs($this->user)->post('/account/data/deletion', ['password' => 'wrong'])
        ->assertSessionHasErrors('password');
    expect($this->user->fresh()->deletion_requested_at)->toBeNull();

    $this->actingAs($this->user)->post('/account/data/deletion', ['password' => 'password'])
        ->assertRedirect(route('account.data'));
    expect($this->user->fresh()->deletion_requested_at)->not->toBeNull();
});
