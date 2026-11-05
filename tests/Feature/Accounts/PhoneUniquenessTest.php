<?php

use App\Models\User;
use App\Notifications\RegistrationAttemptOnAccount;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

/*
| الجوال فريد على كل الحسابات، موثقة أو لا، فهو معرّف الدخول (قرار صفوان، 5 نوفمبر 2026 — decisions/2026-11-05-phone-login.md).
| ويحل محل التفرد على الموثق وحده (14 أكتوبر 2026). والرقم المستخدم برسالة عامة لا تقول أي الحقلين يخص حساباً.
*/

beforeEach(function () {
    Notification::fake();
});

function registerWithPhone(?string $email): TestResponse
{
    return test()->post('/register', [
        'name' => 'عبدالله محمد',
        'email' => $email,
        'phone' => '0512345678',
        'password' => 'Long-Enough-Pass-2026',
        'password_confirmation' => 'Long-Enough-Pass-2026',
        'privacy' => '1',
    ]);
}

it('rejects a number that any account holds, verified or not', function (?string $verifiedAt) {
    User::factory()->create(['phone' => '+966512345678', 'phone_verified_at' => $verifiedAt]);

    // برسالة عامة على البريد، فلا يُعرف أن الرقم يخص حساباً (CreateNewUser — 20 أكتوبر 2026)
    registerWithPhone('owner@example.test')->assertSessionHasErrors(['email' => __('account.register.unavailable')])->assertSessionDoesntHaveErrors('phone');
    // وبلا بريد على الجوال، فلا حقل غيره
    registerWithPhone(null)->assertSessionHasErrors(['phone' => __('account.register.unavailable')]);

    expect(User::count())->toBe(1);
})->with(['unverified' => null, 'verified' => '2026-11-01 10:00:00']);

it('tells the number holder by email that someone tried to register with it', function () {
    $holder = User::factory()->create(['phone' => '+966512345678']);

    registerWithPhone(null)->assertSessionHasErrors('phone');

    Notification::assertSentTo($holder, RegistrationAttemptOnAccount::class);
});

it('refuses a profile number that another account holds, verified or not', function () {
    $user = User::factory()->create(['phone' => '+966500000009']);
    $data = fn (string $phone) => ['name' => $user->name, 'email' => $user->email, 'phone' => $phone, 'current_password' => 'password'];

    User::factory()->create(['phone' => '+966512345678', 'phone_verified_at' => null]);
    $this->actingAs($user)->put('/user/profile-information', $data('0512345678'))
        ->assertSessionHasErrors('phone', null, 'updateProfileInformation');

    expect($user->fresh()->phone)->toBe('+966500000009');
});

it('enforces the uniqueness in the database too', function () {
    User::factory()->create(['phone' => '+966512345678']);

    expect(fn () => User::factory()->create(['phone' => '+966512345678']))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('schedules the unverified accounts cleanup daily', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains((string) $event->command, 'ejaz:prune-unverified-accounts'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('0 0 * * *');
});
