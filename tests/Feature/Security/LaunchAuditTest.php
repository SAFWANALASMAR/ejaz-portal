<?php

use App\Enums\RoleName;
use App\Models\AssemblyFile;
use App\Models\AssemblyMembership;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/*
| الاختبارات المانعة لعودة ثغرات الفحص الأمني قبل الإطلاق (docs/security/2026-10-10-security-audit.md).
*/

// F2: ملفات المجلس ومحاضره لا تُفتح للأدوار الإدارية قبل تفعيل التحقق الثنائي
it('keeps board files from a board member who has not enabled two-factor', function () {
    Storage::fake('local');
    $file = AssemblyFile::factory()->forBoard()->create();
    Storage::disk('local')->put((string) $file->file, pdfContents());

    $board = User::factory()->create();
    $board->assignRole(RoleName::BoardMember->value);

    $this->actingAs($board)->get(route('assembly.files.index'))->assertRedirect(route('account.security'));
    $this->actingAs($board)
        ->get(URL::temporarySignedRoute('assembly.files.download', now()->addMinutes(10), ['file' => $file]))
        ->assertRedirect(route('account.security'));
});

it('still opens the area for a board member with two-factor and for an ordinary assembly member', function () {
    $this->actingAs(userWithRole(RoleName::BoardMember))->get(route('assembly.files.index'))->assertOk();

    $member = AssemblyMembership::factory()->create()->user;
    $this->actingAs($member)->get(route('assembly.files.index'))->assertOk();
});

// F3: لا كعكة «تذكّرني» للأدوار الإدارية، فلا يُعاد الدخول إلى اللوحة بلا كلمة مرور ولا تحقق ثنائي
it('ignores remember me for an admin role', function () {
    $admin = User::factory()->create(['email' => 'admin@example.test']);
    $admin->assignRole(RoleName::TechnicalAdmin->value);
    $token = $admin->remember_token;

    $response = $this->post('/login', ['login' => 'admin@example.test', 'password' => 'password', 'remember' => '1']);

    $this->assertAuthenticatedAs($admin);
    $response->assertCookieMissing(Auth::guard('web')->getRecallerName());
    expect($admin->fresh()->remember_token)->toBe($token);
});

it('does not carry remember me into the two-factor challenge for an admin role', function () {
    $editor = userWithRole(RoleName::ContentEditor, ['email' => 'editor@example.test']);

    $this->post('/login', ['login' => 'editor@example.test', 'password' => 'password', 'remember' => '1'])
        ->assertRedirect('/two-factor-challenge');

    expect(session('login.id'))->toBe($editor->id)
        ->and(session('login.remember'))->toBeFalse();
});

it('keeps remember me for an ordinary member', function () {
    User::factory()->create(['email' => 'member@example.test']);

    $this->post('/login', ['login' => 'member@example.test', 'password' => 'password', 'remember' => '1'])
        ->assertCookie(Auth::guard('web')->getRecallerName());
});

// F4: تغيير كلمة المرور يُنهي جلسات الحساب الأخرى في الموقع، وتبقى جلسة من غيّرها
it('logs out other site sessions after the password changes', function () {
    $user = User::factory()->create();

    // جلسة قديمة تحمل بصمة كلمة المرور السابقة
    $this->actingAs($user)->withSession(['password_hash_web' => Hash::make('old-hash')])
        ->get(route('account.dashboard'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('keeps the session of the user who changed the password', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('account.dashboard'))->assertOk();

    $this->withSession(['auth.password_confirmed_at' => time()])
        ->put('/user/password', [
            'current_password' => 'password',
            'password' => 'a-New-strong-pass-2026',
            'password_confirmation' => 'a-New-strong-pass-2026',
        ])->assertSessionHasNoErrors();

    $this->get(route('account.dashboard'))->assertOk();
    $this->assertAuthenticatedAs($user);
});

// F7: كعكة الجلسة آمنة افتراضياً في الإنتاج
it('marks the session cookie secure by default in production', function () {
    $previous = [$_ENV['APP_ENV'] ?? null, $_SERVER['APP_ENV'] ?? null, getenv('APP_ENV')];
    $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'production';
    putenv('APP_ENV=production');

    try {
        $config = require base_path('config/session.php');
    } finally {
        [$_ENV['APP_ENV'], $_SERVER['APP_ENV']] = [$previous[0], $previous[1]];
        putenv('APP_ENV='.($previous[2] === false ? '' : $previous[2]));
    }

    expect($config['secure'])->toBeTrue();
})->skip(fn (): bool => getenv('SESSION_SECURE_COOKIE') !== false, 'SESSION_SECURE_COOKIE مضبوط في البيئة');
