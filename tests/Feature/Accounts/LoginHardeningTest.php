<?php

use App\Actions\Accounts\NoticeNewDeviceLogin;
use App\Models\User;
use App\Notifications\NewDeviceLogin;
use Illuminate\Support\Facades\Notification;

/*
| تشديد الدخول (قرار صفوان، 20 أكتوبر 2026 — docs/plans/auth-hardening.md): تنبيه الدخول من متصفح جديد، وتجديد معرّف
| الجلسة، وانتهاء جلسة المستفيد بالخمول أطول من اللوحة. والقفل وحدود الشبكة والتحقق الثنائي ومنع «تذكّرني» للإدارة
| في LoginTest وLoginNetworkLimitTest وPanelAccessTest وLaunchAuditTest.
*/

beforeEach(function () {
    Notification::fake();
    $this->chrome = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0 Safari/537.36';
});

function signInFrom(User $user, ?string $known = null)
{
    $request = test()->withHeader('User-Agent', test()->chrome);

    if ($known !== null) {
        $request->withCookie(NoticeNewDeviceLogin::COOKIE, $known);
    }

    $response = $request->post('/login', ['login' => $user->email, 'password' => 'password']);
    auth()->logout();

    return $response;
}

it('emails the owner on a sign in from a browser the account never used, once per browser', function () {
    $user = User::factory()->create(['last_login_at' => now()->subDay()]);

    $first = signInFrom($user);
    Notification::assertSentToTimes($user, NewDeviceLogin::class, 1);
    Notification::assertSentTo($user, NewDeviceLogin::class, fn (NewDeviceLogin $notification) => str_contains($notification->toMail($user)->introLines[0], 'Chrome — Windows'));

    $known = $first->getCookie(NoticeNewDeviceLogin::COOKIE)?->getValue();
    expect($known)->toBe((string) $user->id);

    signInFrom($user, $known);
    Notification::assertSentToTimes($user, NewDeviceLogin::class, 1);
});

it('does not email on the first sign in of a new account, nor for another account on the same browser', function () {
    $fresh = User::factory()->create(['last_login_at' => null]);
    $other = User::factory()->create(['last_login_at' => now()->subDay()]);

    $known = signInFrom($fresh)->getCookie(NoticeNewDeviceLogin::COOKIE)?->getValue();
    Notification::assertNothingSentTo($fresh);

    // متصفح يعرفه حساب آخر جديد على هذا الحساب
    $both = signInFrom($other, $known)->getCookie(NoticeNewDeviceLogin::COOKIE)?->getValue();
    Notification::assertSentTo($other, NewDeviceLogin::class);
    expect($both)->toBe($fresh->id.'.'.$other->id);
});

it('ignores a tampered known browsers cookie and treats the browser as new', function () {
    $user = User::factory()->create(['last_login_at' => now()->subDay()]);

    signInFrom($user, $user->id.';drop');

    Notification::assertSentTo($user, NewDeviceLogin::class);
});

it('gives a new session id on sign in, so a planted session cannot be reused', function () {
    $user = User::factory()->create();
    $this->get('/login');
    $before = session()->getId();

    $this->post('/login', ['login' => $user->email, 'password' => 'password'])->assertRedirect();

    expect(session()->getId())->not->toBe($before);
});

it('ends a member session after the idle lifetime, longer than the panel idle limit', function () {
    expect((int) config('session.lifetime'))->toBeGreaterThan((int) config('security.admin_idle_minutes'))
        ->and((bool) config('session.expire_on_close'))->toBeFalse()
        ->and((bool) config('session.http_only'))->toBeTrue()
        ->and(config('session.same_site'))->toBe('lax');

    // الجلسة في ملف، ويُنسى الحارس ومدير الجلسات بين الطلبات، فلا يبقى مسجلاً إلا بجلسته المحفوظة (FinalAuditTest)
    config(['session.driver' => 'file']);
    app('session')->forgetDrivers();
    $user = User::factory()->create(['password' => 'Idle-Strong-Pass-2026']);
    $cookie = config('session.cookie');
    $session = $this->post('/login', ['login' => $user->email, 'password' => 'Idle-Strong-Pass-2026'])->getCookie($cookie, false)->getValue();
    $file = storage_path('framework/sessions/'.session()->getId());

    // ملف الجلسة يُكتب بساعة الجهاز لا بالوقت المقدَّم في الاختبار، فيُضبط زمنه على وقت الزيارة. والحارس يأخذ
    // session.store من الحاوية، فتُنسى نسختها كذلك، وإلا بقي الدخول في ذاكرتها بعد انتهاء الملف
    $visit = function () use ($cookie, $session, $file) {
        app('auth')->forgetGuards();
        app('session')->forgetDrivers();
        app()->forgetInstance('session.store');
        clearstatcache();
        $response = $this->withUnencryptedCookie($cookie, $session)->get('/account');
        touch($file, now()->getTimestamp());

        return $response;
    };

    touch($file, now()->getTimestamp());
    $lifetime = (int) config('session.lifetime');

    // الخمول يُحسب من آخر طلب لا من الدخول
    $this->travel($lifetime - 5)->minutes();
    $visit()->assertOk();
    $this->travel($lifetime - 5)->minutes();
    $visit()->assertOk();

    $this->travel($lifetime + 1)->minutes();
    $visit()->assertRedirect(route('login'));
});

it('sends the session cookie secure in production', function () {
    $env = [$_SERVER['APP_ENV'] ?? null, $_ENV['APP_ENV'] ?? null];
    $_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'production';

    try {
        $config = require base_path('config/session.php');
    } finally {
        [$_SERVER['APP_ENV'], $_ENV['APP_ENV']] = $env;
    }

    expect($config['secure'])->toBeTrue();
});
