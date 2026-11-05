<?php

use App\Models\User;
use App\Support\PasswordPolicy;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\NotPwnedVerifier;

/*
| بطء الدخول والتسجيل (ملاحظات المدير التنفيذي، 4 نوفمبر 2026 — مدخلات-المدير/بطء-الدخول-2026-11-05.md): فحص التسريب في طلب التسجيل
| بمهلة 3 ثوانٍ لا 30 (مهلة Laravel الافتراضية)، والفحص نفسه وسلوك فشله كما هما، وسقف لاستعلامات طلبي الدخول والتسجيل.
*/

const AUTH_SPEED_PASSWORD = 'Speed-Check-2026!x';

function registerForm(array $overrides = []): array
{
    return ['name' => 'حساب قياس الأداء', 'phone' => '0551234567', 'password' => AUTH_SPEED_PASSWORD, 'password_confirmation' => AUTH_SPEED_PASSWORD, 'privacy' => '1', ...$overrides];
}

it('checks leaked passwords with a short timeout instead of the 30 second default', function () {
    $verifier = app(UncompromisedVerifier::class);

    expect($verifier)->toBeInstanceOf(NotPwnedVerifier::class)
        ->and((fn () => $this->timeout)->call($verifier))->toBe(PasswordPolicy::UNCOMPROMISED_TIMEOUT)
        ->and(PasswordPolicy::UNCOMPROMISED_TIMEOUT)->toBeLessThanOrEqual(5);
});

it('still rejects a leaked password at registration', function () {
    $this->fakeLeakedPassword(AUTH_SPEED_PASSWORD);

    $this->post('/register', registerForm())->assertSessionHasErrors('password');

    expect(User::query()->where('name', 'حساب قياس الأداء')->exists())->toBeFalse();
});

it('keeps Laravel\'s fail-open behaviour when the leak service cannot be reached', function () {
    // مصنع جديد بلا تزييف TestCase، فيصل الفحص إلى الاتصال المتعثّر
    Http::swap(new HttpFactory);
    Http::preventStrayRequests();
    $called = false;
    Http::fake(['api.pwnedpasswords.com/*' => function () use (&$called) {
        $called = true;

        throw new ConnectionException('timed out');
    }]);

    $this->post('/register', registerForm())->assertSessionHasNoErrors()->assertRedirect();

    expect($called)->toBeTrue()
        ->and(User::query()->where('name', 'حساب قياس الأداء')->exists())->toBeTrue();
});

it('keeps the login and register requests within a fixed number of queries', function () {
    User::factory()->create(['username' => 'speed_user', 'password' => Hash::make(AUTH_SPEED_PASSWORD)]);

    DB::enableQueryLog();
    $this->post('/login', ['login' => 'speed_user', 'password' => AUTH_SPEED_PASSWORD])->assertRedirect(route('account.dashboard'));
    $login = count(DB::getQueryLog());
    $this->post('/logout');

    DB::flushQueryLog();
    $this->post('/register', registerForm())->assertSessionHasNoErrors();
    $register = count(DB::getQueryLog());
    DB::disableQueryLog();

    // 10 للدخول و12 للتسجيل عند كتابته (وكان التسجيل 13 بإعادة التحقق): الحساب، وحدود المحاولات، والجلسة، وسجل العمليات
    expect($login)->toBeLessThanOrEqual(10)->and($register)->toBeLessThanOrEqual(12);
});

// كان التحقق يعاد بعد fails() بـ validate()، فيُستدعى فحص التسريب مرتين في كل تسجيل ناجح
it('calls the leak service once per successful registration', function () {
    $this->post('/register', registerForm())->assertSessionHasNoErrors()->assertRedirect();

    expect(Http::recorded(fn ($request) => str_contains($request->url(), 'api.pwnedpasswords.com'))->count())->toBe(1);
});

it('still shows the taken-phone message and other errors on a failed registration', function () {
    User::factory()->create(['phone' => '+966551234567']);

    $this->post('/register', registerForm(['password_confirmation' => 'different']))
        ->assertSessionHasErrors(['phone' => __('account.register.unavailable'), 'password']);
});
