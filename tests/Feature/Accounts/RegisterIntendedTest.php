<?php

use App\Models\Competition;
use App\Models\Program;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Tests\Fakes\FakeSmsGateway;

/*
| ملاحظة المراجعة على #96: من ينشئ حساباً من صفحة تطلب الدخول (التسجيل في مسابقة أو برنامج) يعود إليها بعد التسجيل لا إلى
| لوحته، ومع توثيق الجوال المفعّل يمر بالتوثيق أولاً ثم يعود إليها (RegisterResponse). و«إنشاء حساب» يربط بالصفحة نفسها
| بـ signup=1 فتُحفظ وجهتها في الجلسة من الخادم، ولا معامل يحمل رابطاً.
*/

beforeEach(function () {
    $this->sms = FakeSmsGateway::swap();
    Notification::fake();
    config(['competitions.exam.national_id_key' => 'test-hmac-key']);
    Competition::factory()->student()->create(['slug' => 'brahin', 'is_published' => true]);
    $this->target = route('competitions.student.register', ['slug' => 'brahin', 'signup' => 1]);
});

/**
 * @return array<string, string>
 */
function intendedRegistration(array $overrides = []): array
{
    return [
        'name' => 'عبدالله محمد',
        'email' => 'student@example.test',
        'phone' => '0512345678',
        'password' => 'Long-Enough-Pass-2026',
        'password_confirmation' => 'Long-Enough-Pass-2026',
        'username' => uniqueUsername(), 'privacy' => '1',
        ...$overrides,
    ];
}

it('links «create account» on the competition page to the registration page that remembers it', function () {
    $this->get('/competitions/brahin')->assertOk()->assertSee('href="'.e($this->target).'"', false);

    $this->get($this->target)->assertRedirect(route('register'));
    expect(session('url.intended'))->toBe($this->target);
});

it('brings a new account back to the competition registration when verification is off', function () {
    $this->get($this->target)->assertRedirect(route('register'));

    $this->post('/register', intendedRegistration())->assertRedirect($this->target);
    $this->get($this->target)->assertOk();
});

it('verifies the phone first, then brings the account back to the competition registration', function () {
    enableVerification();
    $this->get($this->target)->assertRedirect(route('register'));

    $this->post('/register', intendedRegistration())->assertRedirect(route('account.phone.verify'));
    expect(session('url.intended'))->toBe($this->target);

    $this->post('/account/verify-phone', ['code' => $this->sms->lastCodeFor('+966512345678')])
        ->assertRedirect($this->target);
});

it('brings a new account back to a program registration', function () {
    Program::factory()->create(['slug' => 'research-skills']);
    $target = route('programs.register', ['slug' => 'research-skills', 'signup' => 1]);

    $this->get($target)->assertRedirect(route('register'));
    $this->post('/register', intendedRegistration())->assertRedirect($target);
    $this->get($target)->assertOk();
});

it('still sends a new account without a saved page to their dashboard', function () {
    $this->post('/register', intendedRegistration())->assertRedirect(route('account.dashboard'));
});

it('drops an admin link left in the session for a new account that cannot enter the panel', function () {
    $this->withSession(['url.intended' => url('/admin/competitions/1')])
        ->post('/register', intendedRegistration())->assertRedirect(route('account.dashboard'));

    expect(session('url.intended'))->toBeNull();
});

it('does not follow an outside address passed in the query', function () {
    // لا معامل وجهة: signup وحده يختار صفحة الدخول أو التسجيل، والوجهة الصفحة المطلوبة نفسها
    $this->get('/register?redirect=https://evil.example/&intended=//evil.example')->assertOk();
    $this->get('/login?signup=1&redirect=https://evil.example/')->assertOk();

    $this->post('/register', intendedRegistration())->assertRedirect(route('account.dashboard'));
    expect(User::where('email', 'student@example.test')->exists())->toBeTrue();
});

it('keeps sending a guest to the login page without signup', function () {
    $this->get(route('account.programs.index'))->assertRedirect(route('login'));
    $this->get(route('account.programs.index', ['signup' => 'yes']))->assertRedirect(route('login'));
});
