<?php

use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

/*
| ملاحظات التجربة الشاملة (18 أكتوبر 2026، الخلل 5): رموز Fortify الخام (profile-information-updated وأخواتها)
| لا تظهر في رسائل الجلسة (components/flash)، فتبقى رسالة الصفحة العربية وحدها، مرة واحدة.
*/

beforeEach(function () {
    $this->user = User::factory()->create(['name' => 'سارة أحمد', 'phone' => '+966512345678', 'phone_verified_at' => now()]);
});

function expectOneArabicMessage(TestResponse $response, string $message, string $code): void
{
    $html = (string) $response->assertOk()->getContent();

    expect(substr_count($html, $message))->toBe(1)
        ->and($html)->not->toContain($code);
}

it('shows one Arabic message after saving the profile', function () {
    $response = $this->actingAs($this->user)->from(route('account.profile'))->followingRedirects()
        ->put('/user/profile-information', ['name' => 'سارة أحمد علي', 'email' => $this->user->email, 'phone' => '0512345678']);

    expectOneArabicMessage($response, __('account.profile.saved'), 'profile-information-updated');
});

it('shows one Arabic message after changing the password', function () {
    $response = $this->actingAs($this->user)->from(route('account.profile'))->followingRedirects()
        ->put('/user/password', ['current_password' => 'password', 'password' => 'Ejaz-Strong-Pass-2026!', 'password_confirmation' => 'Ejaz-Strong-Pass-2026!']);

    expectOneArabicMessage($response, __('account.profile.password_saved'), 'password-updated');
});

it('shows one Arabic message for each two-factor status on the security page', function (string $status) {
    $response = $this->actingAs($this->user)
        ->withSession(['auth.password_confirmed_at' => time(), 'status' => $status])
        ->get(route('account.security'));

    expectOneArabicMessage($response, __('account.security.status.'.$status), $status);
})->with(['two-factor-authentication-enabled', 'two-factor-authentication-disabled', 'recovery-codes-generated']);

it('shows one Arabic message after resending the email verification link', function () {
    Notification::fake();
    $this->user->forceFill(['email_verified_at' => null])->save();

    $response = $this->actingAs($this->user)->from(route('verification.notice'))->followingRedirects()
        ->post(route('verification.send'));

    expectOneArabicMessage($response, __('account.verify_email.sent'), 'verification-link-sent');
});

it('confirms the resent verification link on the dashboard it was sent from', function () {
    Notification::fake();
    $this->user->forceFill(['email_verified_at' => null])->save();

    $response = $this->actingAs($this->user)->from(route('account.dashboard'))->followingRedirects()
        ->post(route('verification.send'));

    expectOneArabicMessage($response, __('account.verify_email.sent'), 'verification-link-sent');
});

it('still shows the Arabic messages the portal itself sends', function () {
    $this->actingAs($this->user)->withSession(['status' => __('account.phone.sent')])->get(route('account.dashboard'))
        ->assertOk()->assertSee(__('account.phone.sent'));
});
