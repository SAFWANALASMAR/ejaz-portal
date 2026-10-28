<?php

use App\Actions\Fortify\ResetUserPassword;
use App\Enums\RoleName;
use App\Models\User;
use App\Notifications\PasswordChanged;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password as PasswordBroker;
use PragmaRX\Google2FA\Google2FA;
use Tests\Fakes\FakeSmsGateway;

/*
| سياسة كلمة المرور (قرار صفوان، 20 أكتوبر 2026 — docs/plans/auth-hardening.md): 12 حرفاً على الأقل، وحرف، ورقم، ورمز،
| وكبير وصغير متى كان فيها لاتيني، ولم تُسرَّب، ولا تحوي بيانات صاحبها. في التسجيل والتغيير والاستعادة وأمر إنشاء
| المشرف، والحسابات القائمة تُطالَب بالتغيير عند الدخول.
*/

beforeEach(function () {
    FakeSmsGateway::swap();
    Notification::fake();
});

function policyRegistration(string $password): array
{
    return [
        'name' => 'عبدالله Saleh الزهراني',
        'email' => 'Abdulla.Z@example.test',
        'phone' => '0512345678',
        'password' => $password,
        'password_confirmation' => $password,
        'username' => uniqueUsername(), 'privacy' => '1',
    ];
}

it('refuses a password that breaks a rule, with a clear Arabic message', function (string $password, string $key, array $replace = []) {
    $message = __($key, ['attribute' => __('validation.attributes.password'), ...$replace]);

    $this->post('/register', policyRegistration($password))->assertSessionHasErrors(['password' => $message]);

    expect(User::query()->where('email', 'abdulla.z@example.test')->exists())->toBeFalse();
})->with([
    'eleven characters' => ['Short-Pas1!', 'validation.min.string', ['min' => 12]],
    'no number' => ['No-Number-Here!', 'validation.password.numbers'],
    'no symbol' => ['NoSymbolHere2026', 'validation.password.symbols'],
    'no letter' => ['1234-5678-9012', 'validation.password.letters'],
    'Latin without a capital' => ['lowercase-only-2026', 'validation.password.mixed'],
    'Latin without a small letter' => ['UPPERCASE-ONLY-2026', 'validation.password.mixed'],
    'a word of the name' => ['My-Saleh-Pass-2026', 'account.password_rules.personal'],
    'an Arabic word of the name' => ['كلمة-الزهراني-2026', 'account.password_rules.personal'],
    'the email before @' => ['Abdulla.Z-Pass-2026', 'account.password_rules.personal'],
    'the mobile number' => ['Pass-512345678-Aa!', 'account.password_rules.personal'],
    'the mobile in Arabic digits' => ['Pass-٥١٢٣٤٥٦٧٨-Aa!', 'account.password_rules.personal'],
]);

it('refuses a leaked password', function () {
    $this->fakeLeakedPassword('Leaked-Pass-2026!');

    $this->post('/register', policyRegistration('Leaked-Pass-2026!'))
        ->assertSessionHasErrors(['password' => __('validation.password.uncompromised', ['attribute' => __('validation.attributes.password')])]);
});

it('accepts a strong Latin password and an Arabic one without letter case', function (string $password, string $email) {
    $this->post('/register', [...policyRegistration($password), 'email' => $email])->assertSessionHasNoErrors();

    expect(User::query()->where('email', $email)->exists())->toBeTrue();
})->with([
    'Latin' => ['Ejaz-Strong-Pass-2026', 'latin@example.test'],
    'Arabic' => ['كلمة-مرور-قوية-٢٠٢٦', 'arabic@example.test'],
]);

it('applies the policy and the personal data rule to a password change and a reset', function () {
    $user = User::factory()->create(['name' => 'منيرة القحطاني', 'email' => 'munira@example.test', 'phone' => '+966511112222', 'password' => 'Current-Pass-2026!']);

    $this->actingAs($user)->put('/user/password', ['current_password' => 'Current-Pass-2026!', 'password' => 'weak-pass', 'password_confirmation' => 'weak-pass'])
        ->assertSessionHasErrorsIn('updatePassword', 'password');
    $this->actingAs($user)->put('/user/password', ['current_password' => 'Current-Pass-2026!', 'password' => 'Munira-Pass-2026!', 'password_confirmation' => 'Munira-Pass-2026!'])
        ->assertSessionHasErrorsIn('updatePassword', ['password' => __('account.password_rules.personal')]);
    auth()->logout();

    $token = PasswordBroker::broker()->createToken($user);
    $this->post('/reset-password', ['token' => $token, 'email' => 'munira@example.test', 'password' => 'Pass-511112222-Ok', 'password_confirmation' => 'Pass-511112222-Ok'])
        ->assertSessionHasErrors(['password' => __('account.password_rules.personal')]);
    $this->post('/reset-password', ['token' => $token, 'email' => 'munira@example.test', 'password' => 'Fresh-Strong-Pass-2026', 'password_confirmation' => 'Fresh-Strong-Pass-2026'])
        ->assertRedirect('/login');

    expect(Hash::check('Fresh-Strong-Pass-2026', $user->fresh()->password))->toBeTrue();
    $this->assertGuest();
    Notification::assertSentTo($user, PasswordChanged::class);
});

it('applies the policy to the admin creation command', function () {
    $this->artisan('ejaz:create-admin')
        ->expectsQuestion('البريد الإلكتروني', 'portal-admin@example.test')
        ->expectsQuestion('الاسم الكامل', 'مشرف البوابة')
        ->expectsQuestion('كلمة المرور ('.__('account.password_rules.summary').')', 'portal-admin-2026')
        ->expectsQuestion('أعد كتابة كلمة المرور', 'portal-admin-2026')
        ->assertFailed();

    expect(User::query()->where('email', 'portal-admin@example.test')->exists())->toBeFalse();

    $this->artisan('ejaz:create-admin')
        ->expectsQuestion('البريد الإلكتروني', 'portal-admin@example.test')
        ->expectsQuestion('الاسم الكامل', 'مشرف البوابة')
        ->expectsQuestion('كلمة المرور ('.__('account.password_rules.summary').')', 'Strong-Admin-Pass-2026')
        ->expectsQuestion('أعد كتابة كلمة المرور', 'Strong-Admin-Pass-2026')
        ->assertSuccessful();
});

// الحسابات القائمة لا تُكسر: تدخل، ثم لا يُفتح إلا «بياناتي» لتغيير كلمة المرور حتى تُغيَّر
it('asks an existing account with a weak password to change it before anything else, then lets it through', function () {
    $user = User::factory()->create(['email' => 'old-weak@example.test', 'password' => 'oldpassword10']);

    $this->post('/login', ['login' => 'old-weak@example.test', 'password' => 'oldpassword10'])->assertRedirect();
    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->password_upgrade_required_at)->not->toBeNull();

    $this->get('/account')->assertRedirect(route('account.profile').'#password')->assertSessionHas('status', __('account.password_rules.upgrade_required'));
    $this->get('/account/requests')->assertRedirect(route('account.profile').'#password');
    $this->get('/account/profile')->assertOk()->assertSee('data-password-rules', false);

    $this->put('/user/password', ['current_password' => 'oldpassword10', 'password' => 'New-Strong-Pass-2026', 'password_confirmation' => 'New-Strong-Pass-2026'])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->password_upgrade_required_at)->toBeNull();
    $this->get('/account')->assertOk();
});

it('lets an existing account with a password that meets the policy in without asking', function () {
    $user = User::factory()->create(['email' => 'old-strong@example.test', 'password' => 'Already-Strong-Pass-2026']);

    $this->post('/login', ['login' => 'old-strong@example.test', 'password' => 'Already-Strong-Pass-2026']);

    expect($user->fresh()->password_upgrade_required_at)->toBeNull();
    $this->get('/account')->assertOk();
});

it('keeps the request through two-factor, and asks a panel account before the panel', function () {
    $user = User::factory()->withTwoFactor()->create(['email' => 'staff-weak@example.test', 'password' => 'password']);
    $user->assignRole(RoleName::Secretary->value);

    $this->post('/login', ['login' => 'staff-weak@example.test', 'password' => 'password'])->assertRedirect('/two-factor-challenge');
    $this->post('/two-factor-challenge', ['code' => (new Google2FA)->getCurrentOtp('JBSWY3DPEHPK3PXP')]);
    $this->assertAuthenticatedAs($user);

    $this->get('/admin')->assertRedirect(route('account.profile').'#password');
    // و«الأمان» (التحقق الثنائي) يُفتح، بتأكيد كلمة المرور كعادته لا بالمطالبة
    $this->get('/account/security')->assertRedirect(route('password.confirm'));
    $this->get('/account/profile')->assertOk();
});

it('keeps asking on a remember me return and on other sessions, since the request is on the account', function () {
    $user = User::factory()->create(['email' => 'remembered-weak@example.test', 'password' => 'oldpassword10']);

    $this->post('/login', ['login' => 'remembered-weak@example.test', 'password' => 'oldpassword10', 'remember' => '1']);

    // جلسة جديدة بلا شيء من الأولى، كعودة «تذكّرني» أو جهاز آخر
    $this->flushSession();
    auth()->forgetGuards();
    $this->actingAs($user->fresh())->get('/account')->assertRedirect(route('account.profile').'#password');
});

it('does not carry the request to another account signed in within the same session', function () {
    User::factory()->withTwoFactor()->create(['email' => 'weak-first@example.test', 'password' => 'password']);
    $other = User::factory()->create(['email' => 'strong-second@example.test', 'password' => 'Already-Strong-Pass-2026']);

    // الأول يقف عند التحقق الثنائي، ثم يدخل الثاني في الجلسة نفسها
    $this->post('/login', ['login' => 'weak-first@example.test', 'password' => 'password'])->assertRedirect('/two-factor-challenge');
    $this->post('/login', ['login' => 'strong-second@example.test', 'password' => 'Already-Strong-Pass-2026']);

    $this->assertAuthenticatedAs($other);
    $this->get('/account')->assertOk();
});

it('clears the request when the password is reset', function () {
    $user = User::factory()->create(['password' => 'oldpassword10', 'password_upgrade_required_at' => now()]);

    app(ResetUserPassword::class)->reset($user, ['password' => 'Reset-Strong-Pass-2026', 'password_confirmation' => 'Reset-Strong-Pass-2026']);

    expect($user->fresh()->password_upgrade_required_at)->toBeNull();
});
