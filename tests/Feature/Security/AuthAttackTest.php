<?php

use App\Actions\Users\ChangeUserRoles;
use App\Enums\RoleName;
use App\Http\Middleware\AdminIdleTimeout;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use PragmaRX\Google2FA\Google2FA;

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

// «مأخوذ» في «بياناتي» يكشف بريد غيره أو جواله الموثق، وكان بلا حد: خمس قيم مأخوذة في الساعة، ثم رسالة الانتظار
it('limits probing taken emails and phones from the profile to five an hour per account', function () {
    $taken = User::factory()->count(6)->create(['email_verified_at' => now()]);
    $user = User::factory()->create(['name' => 'صاحب الحساب']);
    $this->actingAs($user);
    $probe = fn (string $email) => $this->put('/user/profile-information', ['name' => 'صاحب الحساب', 'email' => $email, 'current_password' => 'password']);

    foreach ($taken->take(5) as $other) {
        $probe($other->email)->assertSessionHasErrorsIn('updateProfileInformation', 'email');
        expect(session('errors')->getBag('updateProfileInformation')->first('email'))->not->toBe(__('account.profile.taken_throttled'));
    }

    $probe($taken->last()->email)->assertSessionHasErrorsIn('updateProfileInformation', ['email' => __('account.profile.taken_throttled')]);

    // ولا يُحفظ بريد جديد حر حتى تنقضي الساعة، فلا يُعرف من النجاح أيضاً
    $probe('free@example.test');
    expect($user->fresh()->email)->not->toBe('free@example.test');

    $this->travel(61)->minutes();
    $probe('free@example.test')->assertSessionHasNoErrors();
});

// الحساب الموقوف كان يرد أسرع بزمن تجزئة كامل: كلمة المرور تُفحص قبل حالة الحساب
it('hashes the password of a suspended account too, so its answer takes the same time', function () {
    User::factory()->create(['email' => 'suspended@example.test', 'is_active' => false]);
    Hash::spy();

    $this->post('/login', ['login' => 'suspended@example.test', 'password' => 'wrong-password']);

    Hash::shouldHaveReceived('check')->withArgs(fn (string $value) => $value === 'wrong-password');
    $this->assertGuest();
});

// مكتبة مفاتيح الدخول مثبتة مع Fortify والميزة غير مفعّلة: تحديث للمكتبة لا يفتح مساراتها بصمت
it('registers no passkey route', function () {
    $passkeys = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_contains($route->uri(), 'passkey') || str_contains((string) $route->getName(), 'passkey'));

    expect($passkeys->map->uri()->values()->all())->toBe([]);
});

it('starts a new session id after the two-factor challenge, not only after the password', function () {
    User::factory()->withTwoFactor()->create(['email' => 'twofa@example.test', 'password' => 'Qx7-strong-Ejaz-pass']);

    $this->post('/login', ['login' => 'twofa@example.test', 'password' => 'Qx7-strong-Ejaz-pass'])->assertRedirect('/two-factor-challenge');
    $before = session()->getId();

    $this->post('/two-factor-challenge', ['code' => (new Google2FA)->getCurrentOtp('JBSWY3DPEHPK3PXP')]);

    $this->assertAuthenticated();
    expect(session()->getId())->not->toBe($before);
});

it('invalidates the session on logout, so the old session id signs nobody in', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/account')->assertOk();
    $old = session()->getId();
    $token = $user->fresh()->remember_token;

    $this->post('/logout')->assertRedirect();

    $this->assertGuest();
    expect(session()->getId())->not->toBe($old)
        ->and($user->fresh()->remember_token)->not->toBe($token);
});

it('ends an idle Horizon session like the panel', function () {
    expect(config('horizon.middleware'))->toContain(AdminIdleTimeout::class);

    $this->actingAs(userWithRole(RoleName::TechnicalAdmin))
        ->withSession([AdminIdleTimeout::SESSION_KEY => now()->subMinutes((int) config('security.admin_idle_minutes') + 1)->getTimestamp()])
        ->get('/horizon')
        ->assertRedirect(route('login'));

    $this->assertGuest();
});
