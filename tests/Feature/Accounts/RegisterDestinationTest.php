<?php

use App\Models\Competition;
use App\Models\Program;
use Illuminate\Support\Facades\Notification;
use Tests\Fakes\FakeSmsGateway;

/*
| بعد إنشاء الحساب إلى «حسابي»، ومع توثيق الجوال المفعّل إلى التوثيق ثم «حسابي»، ولو جاء من صفحة تطلب الدخول (التسجيل في
| مسابقة أو برنامج): لا عودة إلى أي صفحة سابقة (قرار صفوان، 5 نوفمبر 2026 — decisions/2026-11-05-phone-login.md، ويلغي
| العودة بعد التسجيل من ملاحظة المراجعة على #96). و«إنشاء حساب» على تلك الصفحات يبقى يفتح التسجيل بـ signup=1.
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
function destinationRegistration(array $overrides = []): array
{
    return [
        'name' => 'عبدالله محمد',
        'phone' => '0512345678',
        'password' => 'Long-Enough-Pass-2026',
        'password_confirmation' => 'Long-Enough-Pass-2026',
        'privacy' => '1',
        ...$overrides,
    ];
}

it('links «create account» on the competition page to the registration page', function () {
    $this->get('/competitions/brahin')->assertOk()->assertSee('href="'.e($this->target).'"', false);

    $this->get($this->target)->assertRedirect(route('register'));
});

it('sends a new account from the competition page to their account when verification is off', function () {
    $this->get($this->target)->assertRedirect(route('register'));

    $this->post('/register', destinationRegistration())->assertRedirect(route('account.dashboard'));
    expect(session('url.intended'))->toBeNull();
});

it('verifies the phone first, then sends the account to their account, not the competition', function () {
    enableVerification();
    $this->get($this->target)->assertRedirect(route('register'));

    $this->post('/register', destinationRegistration())->assertRedirect(route('account.phone.verify'));
    expect(session('url.intended'))->toBeNull();

    $this->post('/account/verify-phone', ['code' => $this->sms->lastCodeFor('+966512345678')])
        ->assertRedirect(route('account.dashboard'));
});

it('sends a new account from a program registration to their account', function () {
    Program::factory()->create(['slug' => 'research-skills']);
    $target = route('programs.register', ['slug' => 'research-skills', 'signup' => 1]);

    $this->get($target)->assertRedirect(route('register'));
    $this->post('/register', destinationRegistration())->assertRedirect(route('account.dashboard'));
});

it('follows no destination left in the session, own or foreign', function (string $intended) {
    $this->withSession(['url.intended' => $intended])
        ->post('/register', destinationRegistration())->assertRedirect(route('account.dashboard'));

    expect(session('url.intended'))->toBeNull();
})->with([
    'admin link' => [fn () => url('/admin/competitions/1')],
    'account page' => [fn () => route('account.programs.index')],
    'foreign host' => ['https://evil.example/account/programs'],
    'backslash' => [fn () => url('/').'\evil.example'],
]);

it('keeps sending a guest to the login page without signup', function () {
    $this->get(route('account.programs.index'))->assertRedirect(route('login'));
    $this->get(route('account.programs.index', ['signup' => 'yes']))->assertRedirect(route('login'));
});
