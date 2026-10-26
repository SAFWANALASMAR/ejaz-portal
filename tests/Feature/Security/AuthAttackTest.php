<?php

use App\Actions\Users\ChangeUserRoles;
use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;

/*
| هجمات الدخول (الاختبار الأمني 0.14.0): ما لم يغطه تشديد الدخول (20 أكتوبر) ولا مطابقة المعايير.
*/

// كان «الرابط غير صالح» لبريد مسجل و«إن كان البريد مسجلاً…» لغيره، بلا حد: تعداد البُرُد من نموذج كلمة المرور الجديدة
it('answers a wrong reset token the same way for a registered and an unknown email', function () {
    User::factory()->create(['email' => 'known@example.test']);
    $attempt = fn (string $email) => $this->from('/reset-password/x')->post('/reset-password', [
        'token' => 'wrong-token', 'email' => $email, 'password' => 'N3w-Pass-Phrase!', 'password_confirmation' => 'N3w-Pass-Phrase!',
    ]);

    $known = $attempt('known@example.test')->assertSessionHasErrors('email')->getSession()->get('errors')->get('email');
    $unknown = $attempt('nobody@example.test')->assertSessionHasErrors('email')->getSession()->get('errors')->get('email');

    expect($known)->toBe($unknown)->and($known)->toBe([__(Password::INVALID_TOKEN)]);
});

it('rate-limits setting a new password with a reset token', function () {
    $attempt = fn () => $this->post('/reset-password', ['token' => 'x', 'email' => 'a@example.test', 'password' => 'p', 'password_confirmation' => 'p']);

    foreach (range(1, 10) as $ignored) {
        expect($attempt()->status())->not->toBe(429);
    }

    $attempt()->assertTooManyRequests();
});

// كعكة «تذكّرني» صدرت لمستفيد قبل ترقيته إلى دور في اللوحة كانت تفتح /admin بلا كلمة مرور ولا تحقق ثنائي
it('voids a remember me cookie issued before the account got a panel role', function () {
    $user = User::factory()->create(['email' => 'promoted@example.test', 'password' => 'Qx7-strong-Ejaz-pass']);
    $recaller = Auth::guard('web')->getRecallerName();

    $cookie = $this->post('/login', ['login' => 'promoted@example.test', 'password' => 'Qx7-strong-Ejaz-pass', 'remember' => '1'])
        ->assertCookie($recaller)
        ->getCookie($recaller)->getValue();

    $user->forceFill(User::factory()->withTwoFactor()->make()->only(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']))->save();
    app(ChangeUserRoles::class)->handle(userWithRole(RoleName::TechnicalAdmin), $user->fresh(), [RoleName::ContentEditor->value]);

    $this->flushSession();
    Auth::forgetGuards();

    $this->withCookie($recaller, $cookie)->get('/admin')->assertRedirect();
    $this->assertGuest();
});

it('keeps the remember me cookie of a member whose roles change without a panel role', function () {
    $user = User::factory()->create(['email' => 'member2@example.test']);
    $token = $user->remember_token;

    app(ChangeUserRoles::class)->handle(userWithRole(RoleName::TechnicalAdmin), $user, [RoleName::Member->value]);

    expect($user->fresh()->remember_token)->toBe($token);
});
