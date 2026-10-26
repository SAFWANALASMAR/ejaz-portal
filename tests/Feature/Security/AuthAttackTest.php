<?php

use App\Actions\Users\ChangeUserRoles;
use App\Enums\RoleName;
use App\Http\Controllers\Account\NewPasswordController;
use App\Http\Middleware\AdminIdleTimeout;
use App\Http\Middleware\EnsureTwoFactorIsEnabled;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Timebox;
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

// خمس لكل بريد وعنوان، ولا تحجب مدرسةً خلف عنوان واحد: بريد آخر من العنوان نفسه يمر، حتى سقف المشترك الأرحب
it('rate-limits setting a new password per email and address, with a roomier cap per subscriber', function () {
    config(['security.password_update_per_subscriber_per_minute' => 7]);
    $attempt = fn (string $email) => $this->post('/reset-password', ['token' => 'x', 'email' => $email, 'password' => 'p', 'password_confirmation' => 'p']);

    foreach (range(1, 5) as $ignored) {
        expect($attempt('a@example.test')->status())->not->toBe(429);
    }

    $attempt('a@example.test')->assertTooManyRequests();
    expect($attempt('b@example.test')->status())->not->toBe(429)
        ->and($attempt('c@example.test')->status())->not->toBe(429);

    $attempt('d@example.test')->assertTooManyRequests();
});

it('sets a new password in a fixed time, so the time does not tell a registered email', function () {
    $timebox = new class extends Timebox
    {
        /** @var list<int> */
        public array $calls = [];

        public function call(callable $callback, int $microseconds)
        {
            $this->calls[] = $microseconds;

            return $callback($this);
        }
    };
    $this->app->instance(Timebox::class, $timebox);

    $this->post('/reset-password', ['token' => 'x', 'email' => 'nobody@example.test', 'password' => 'p', 'password_confirmation' => 'p']);

    expect($timebox->calls)->toBe([NewPasswordController::MICROSECONDS]);
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

// والجلسة التي استُعيدت بالكعكة قبل الترقية لا يبطلها تدوير الرمز: تُعلَّم، فلا تبلغ اللوحة ولا Horizon
it('signs out a session restored by remember me before the promotion, at the panel and Horizon', function (string $path) {
    $user = User::factory()->create(['email' => 'restored@example.test', 'password' => 'Qx7-strong-Ejaz-pass']);
    $recaller = Auth::guard('web')->getRecallerName();
    $cookie = $this->post('/login', ['login' => 'restored@example.test', 'password' => 'Qx7-strong-Ejaz-pass', 'remember' => '1'])
        ->getCookie($recaller)->getValue();

    // جلسة جديدة بالكعكة وحدها، وهو ما زال مستفيداً
    $this->flushSession();
    Auth::forgetGuards();
    $this->withCookie($recaller, $cookie)->get('/account')->assertOk();
    expect(session(EnsureTwoFactorIsEnabled::VIA_REMEMBER))->toBeTrue();

    $user->forceFill(User::factory()->withTwoFactor()->make()->only(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']))->save();
    app(ChangeUserRoles::class)->handle(userWithRole(RoleName::TechnicalAdmin), $user->fresh(), [RoleName::TechnicalAdmin->value]);
    Auth::forgetGuards();

    $this->get($path)->assertRedirect(route('login'));
    $this->assertGuest();
})->with(['panel' => '/admin', 'horizon' => '/horizon']);

it('keeps a password sign-in of the same account out of the remember me mark', function () {
    User::factory()->create(['email' => 'plain@example.test', 'password' => 'Qx7-strong-Ejaz-pass']);

    $this->post('/login', ['login' => 'plain@example.test', 'password' => 'Qx7-strong-Ejaz-pass', 'remember' => '1']);

    expect(session(EnsureTwoFactorIsEnabled::VIA_REMEMBER))->toBeNull();
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

// لوحة Horizon تقرأ واجهتها كل ثوانٍ، فكانت كل قراءة تُعدّ نشاطاً ولا تنتهي الجلسة والصفحة مفتوحة
it('does not count the Horizon dashboard polls as activity', function () {
    $idle = now()->subMinutes((int) config('security.admin_idle_minutes') - 1)->getTimestamp();
    $this->actingAs(userWithRole(RoleName::TechnicalAdmin))->withSession([AdminIdleTimeout::SESSION_KEY => $idle]);

    $this->get('/horizon/api/stats');

    expect(session(AdminIdleTimeout::SESSION_KEY))->toBe($idle);

    $this->travel(2)->minutes();
    $this->get('/horizon/api/stats')->assertRedirect(route('login'));
    $this->assertGuest();
});

it('ends an idle Horizon session like the panel', function () {
    expect(config('horizon.middleware'))->toContain(AdminIdleTimeout::class);

    $this->actingAs(userWithRole(RoleName::TechnicalAdmin))
        ->withSession([AdminIdleTimeout::SESSION_KEY => now()->subMinutes((int) config('security.admin_idle_minutes') + 1)->getTimestamp()])
        ->get('/horizon')
        ->assertRedirect(route('login'));

    $this->assertGuest();
});
