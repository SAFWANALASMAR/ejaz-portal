<?php

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

beforeEach(fn () => Notification::fake());

it('shows the same message whether or not the email is registered', function () {
    $user = User::factory()->create(['email' => 'member@example.test']);

    $this->from('/forgot-password')->post('/forgot-password', ['email' => 'member@example.test'])
        ->assertSessionHas('status', __('passwords.sent'));

    $this->from('/forgot-password')->post('/forgot-password', ['email' => 'nobody@example.test'])
        ->assertSessionHas('status', __('passwords.sent'))
        ->assertSessionHasNoErrors();

    Notification::assertSentTo($user, ResetPasswordNotification::class);
    Notification::assertCount(1);
});

it('resets the password with a valid token only once', function () {
    $user = User::factory()->create(['email' => 'member@example.test']);
    $token = Password::broker()->createToken($user);

    $payload = [
        'token' => $token,
        'email' => 'member@example.test',
        'password' => 'brand-new-pass-2026',
        'password_confirmation' => 'brand-new-pass-2026',
    ];

    $this->post('/reset-password', $payload)->assertRedirect('/login');
    expect(Hash::check('brand-new-pass-2026', $user->fresh()->password))->toBeTrue();

    $this->post('/reset-password', [...$payload, 'password' => 'another-pass-2026', 'password_confirmation' => 'another-pass-2026'])
        ->assertSessionHasErrors('email');
});
