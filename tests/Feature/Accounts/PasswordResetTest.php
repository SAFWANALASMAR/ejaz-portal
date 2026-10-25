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

it('caps reset-link requests per network, so one source cannot mail every account', function () {
    config(['security.password_reset_per_subscriber_per_hour' => 2]);

    foreach (['a', 'b'] as $name) {
        $this->from('/forgot-password')->post('/forgot-password', ['email' => "{$name}@example.test"])->assertRedirect('/forgot-password');
    }

    $this->post('/forgot-password', ['email' => 'c@example.test'])->assertTooManyRequests();
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->from('/forgot-password')
        ->post('/forgot-password', ['email' => 'c@example.test'])->assertRedirect('/forgot-password');
});

it('resets the password with a valid token only once', function () {
    $user = User::factory()->create(['email' => 'member@example.test']);
    $token = Password::broker()->createToken($user);

    $payload = [
        'token' => $token,
        'email' => 'member@example.test',
        'password' => 'Brand-New-Pass-2026',
        'password_confirmation' => 'Brand-New-Pass-2026',
    ];

    $this->post('/reset-password', $payload)->assertRedirect('/login');
    expect(Hash::check('Brand-New-Pass-2026', $user->fresh()->password))->toBeTrue();

    $this->post('/reset-password', [...$payload, 'password' => 'Another-Pass-2026', 'password_confirmation' => 'Another-Pass-2026'])
        ->assertSessionHasErrors('email');
});
