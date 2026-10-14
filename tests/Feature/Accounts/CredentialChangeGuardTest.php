<?php

use App\Models\User;
use App\Notifications\PasswordChanged;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

/*
 * تغيير كلمة المرور يُنبَّه به صاحب الحساب، وتخمين كلمة المرور الحالية من جلسة مسروقة يُقفل بعد خمس
 * محاولات خاطئة في ربع ساعة، على المسارين معاً وقبل فحص التجزئة (الفحص الأمني، 14 أكتوبر 2026).
 */

beforeEach(function () {
    $this->user = User::factory()->create([
        'email' => 'sara@example.test',
        'phone' => '+966512345678',
        'phone_verified_at' => now(),
    ]);
});

function passwordChange(string $current): array
{
    return [
        'current_password' => $current,
        'password' => 'brand-new-pass-2026',
        'password_confirmation' => 'brand-new-pass-2026',
    ];
}

it('tells the account owner when the password changes', function () {
    Notification::fake();

    $this->actingAs($this->user)->put('/user/password', passwordChange('password'))->assertSessionHasNoErrors();

    Notification::assertSentTo($this->user, PasswordChanged::class);

    $mail = (new PasswordChanged)->toMail($this->user);
    expect($mail->subject)->toBe(__('account.password_changed_mail.subject'))
        ->and($mail->introLines)->toContain(__('account.password_changed_mail.body'));
});

it('sends nothing when the current password is wrong', function () {
    Notification::fake();

    $this->actingAs($this->user)->put('/user/password', passwordChange('wrong-password'))
        ->assertSessionHasErrorsIn('updatePassword', 'current_password');

    Notification::assertNothingSent();
});

it('locks the password change after five wrong current passwords, before checking the hash', function () {
    foreach (range(1, 5) as $ignored) {
        $this->actingAs($this->user)->put('/user/password', passwordChange('wrong-password'))
            ->assertSessionHasErrorsIn('updatePassword', 'current_password');
    }

    Hash::spy();

    $this->actingAs($this->user)->put('/user/password', passwordChange('password'))
        ->assertSessionHasErrorsIn('updatePassword', ['current_password' => __('account.confirm_password.throttle', ['minutes' => 15])]);

    Hash::shouldNotHaveReceived('check');
    expect(password_verify('password', $this->user->fresh()->password))->toBeTrue();
});

it('locks the contact change after five wrong current passwords', function () {
    $change = ['name' => 'سارة', 'email' => 'thief@example.test', 'phone' => '0512345678'];

    foreach (range(1, 5) as $ignored) {
        $this->actingAs($this->user)->put('/user/profile-information', [...$change, 'current_password' => 'wrong-password'])
            ->assertSessionHasErrorsIn('updateProfileInformation', 'current_password');
    }

    $this->actingAs($this->user)->put('/user/profile-information', [...$change, 'current_password' => 'password'])
        ->assertSessionHasErrorsIn('updateProfileInformation', ['current_password' => __('account.confirm_password.throttle', ['minutes' => 15])]);

    expect($this->user->fresh()->email)->toBe('sara@example.test');
});

it('counts wrong guesses on both routes and the confirmation together', function () {
    foreach (range(1, 2) as $ignored) {
        $this->actingAs($this->user)->put('/user/password', passwordChange('wrong-password'));
        $this->actingAs($this->user)->post('/user/confirm-password', ['password' => 'wrong-password']);
    }
    $this->actingAs($this->user)->put('/user/profile-information', [
        'name' => 'سارة', 'email' => 'thief@example.test', 'phone' => '0512345678', 'current_password' => 'wrong-password',
    ]);

    $this->actingAs($this->user)->put('/user/password', passwordChange('password'))
        ->assertSessionHasErrorsIn('updatePassword', ['current_password' => __('account.confirm_password.throttle', ['minutes' => 15])]);
});

it('still saves a plain name change without asking for the password', function () {
    foreach (range(1, 5) as $ignored) {
        $this->actingAs($this->user)->put('/user/password', passwordChange('wrong-password'));
    }

    $this->actingAs($this->user)->put('/user/profile-information', [
        'name' => 'سارة أحمد', 'email' => 'sara@example.test', 'phone' => '0512345678',
    ])->assertSessionHasNoErrors();

    expect($this->user->fresh()->name)->toBe('سارة أحمد');
});
