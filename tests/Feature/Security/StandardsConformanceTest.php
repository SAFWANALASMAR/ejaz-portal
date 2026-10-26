<?php

use App\Enums\ActivityEvent;
use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

/*
| ما أُصلح من مطابقة ASVS 4.0 المستوى الثاني (docs/security/2026-10-20-مطابقة-المعايير.md): حد كلمة المرور بما يجزّئه
| bcrypt كاملاً (2.1.2)، والخروج من الأجهزة الأخرى (3.3.4)، ومدة «تذكّرني» (3.3.2)، وبادئة __Host- لكعكة الجلسة
| (3.4.4)، وتسجيل رفض الوصول (7.2.2).
*/

beforeEach(fn () => Notification::fake());

it('refuses a password longer than bcrypt reads, Arabic or Latin, and accepts one that fits', function (string $password, bool $fits) {
    $response = $this->post('/register', [
        'name' => 'مستفيد تجريبي',
        'email' => 'long@example.test',
        'phone' => '0512345670',
        'password' => $password,
        'password_confirmation' => $password,
        'username' => uniqueUsername(), 'privacy' => '1',
    ]);

    $fits
        ? $response->assertSessionDoesntHaveErrors('password')
        : $response->assertSessionHasErrors(['password' => __('account.password_rules.too_long')]);
})->with([
    // 35 حرفاً عربياً وأربعة لاتينية = 74 بايتاً، وما بعد البايت 72 كان يُهمل
    'Arabic past 72 bytes' => [str_repeat('ب', 35).'Ab1!', false],
    'Latin past 72 bytes' => [str_repeat('x', 70).'Ab1!', false],
    'exactly 72 bytes' => [str_repeat('ب', 34).'Ab1!', true],
]);

it('signs out every other session with the current password, and keeps this one', function () {
    $user = User::factory()->create(['password' => 'Current-Strong-Pass-2026', 'remember_token' => 'old-remember']);
    $oldHash = $user->password;

    $this->actingAs($user)->post(route('account.security.other-sessions'), ['current_password' => 'wrong-password'])
        ->assertSessionHasErrors('current_password', null, 'logoutOtherSessions');
    expect($user->fresh()->password)->toBe($oldHash);

    $this->actingAs($user)->post(route('account.security.other-sessions'), ['current_password' => 'Current-Strong-Pass-2026'])
        ->assertRedirect(route('account.security'))
        ->assertSessionHas('status', __('account.security.other_sessions.done'));

    $user->refresh();
    expect($user->password)->not->toBe($oldHash)
        ->and(Hash::check('Current-Strong-Pass-2026', $user->password))->toBeTrue()
        ->and($user->remember_token)->not->toBe('old-remember')
        ->and(Activity::query()->where('event', ActivityEvent::OtherSessionsEnded->value)->whereMorphedTo('subject', $user)->exists())->toBeTrue();

    // جلسة أخرى ببصمة كلمة المرور القديمة تنتهي (AuthenticateSession)
    app('auth')->forgetGuards();
    $this->withSession(['password_hash_web' => $oldHash])->actingAs($user)->get('/account')->assertRedirect(route('login'));
});

it('keeps remember me for 30 days, not the framework default of 400', function () {
    $user = User::factory()->create(['password' => 'Remember-Strong-Pass-2026']);

    $cookie = collect($this->post('/login', ['login' => $user->email, 'password' => 'Remember-Strong-Pass-2026', 'remember' => '1'])->headers->getCookies())
        ->first(fn ($cookie) => str_starts_with($cookie->getName(), 'remember_web_'));

    expect($cookie)->not->toBeNull()
        ->and($cookie->getExpiresTime() - time())->toBeLessThanOrEqual(60 * 60 * 24 * 30)
        ->toBeGreaterThan(60 * 60 * 24 * 29);
});

it('names the session cookie with the __Host- prefix in production', function () {
    $env = [$_SERVER['APP_ENV'] ?? null, $_ENV['APP_ENV'] ?? null];
    $_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'production';

    try {
        $config = require base_path('config/session.php');
    } finally {
        [$_SERVER['APP_ENV'], $_ENV['APP_ENV']] = $env;
    }

    // والبادئة تشترط الكعكة الآمنة على المسار / بلا نطاق
    expect($config['cookie'])->toStartWith('__Host-')
        ->and($config['secure'])->toBeTrue()
        ->and($config['path'])->toBe('/')
        ->and($config['domain'])->toBeNull()
        ->and(config('session.cookie'))->not->toStartWith('__Host-');
});

it('logs every access denial with the account and the route name', function () {
    Log::spy();
    $member = userWithRole(RoleName::Member);

    $this->actingAs($member)->get('/admin/users')->assertForbidden();

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context) => $message === 'Access denied'
        && $context['user_id'] === $member->id
        && $context['route'] === 'filament.admin.resources.users.index');
});

// لا واجهة برمجية، فلا رأس CORS على أي مسار. وإعداد لارافيل الافتراضي كان يرد «*» على /api/* (اختبار الاختراق الآلي)
it('sends no CORS header, on the old API prefix or anywhere else', function (string $method, string $path) {
    $response = $this->call($method, $path, server: [
        'HTTP_ORIGIN' => 'https://attacker.example',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
    ]);

    expect($response->headers->has('Access-Control-Allow-Origin'))->toBeFalse();
})->with([
    'api GET' => ['GET', '/api/soap'],
    'api preflight' => ['OPTIONS', '/api/users'],
    'home' => ['GET', '/'],
]);
