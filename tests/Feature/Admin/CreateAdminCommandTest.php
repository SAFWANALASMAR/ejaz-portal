<?php

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Testing\PendingCommand;

function runCreateAdmin(string $email, string $password, ?string $confirmation = null): PendingCommand
{
    return test()->artisan('ejaz:create-admin')
        ->expectsQuestion('البريد الإلكتروني', $email)
        ->expectsQuestion('الاسم الكامل', 'مشرف البوابة')
        ->expectsQuestion('كلمة المرور ('.__('account.password_rules.summary').')', $password)
        ->expectsQuestion('أعد كتابة كلمة المرور', $confirmation ?? $password);
}

it('creates a verified technical admin by default', function () {
    runCreateAdmin('Admin@Example.test', 'a-Strong-pass-2026')->assertSuccessful();

    $admin = User::where('email', 'admin@example.test')->firstOrFail();

    expect($admin->hasRole(RoleName::TechnicalAdmin->value))->toBeTrue()
        ->and($admin->hasVerifiedEmail())->toBeTrue();
});

it('rejects a short password', function () {
    runCreateAdmin('admin@example.test', 'short')->assertFailed();

    expect(User::count())->toBe(0);
});

it('rejects a mismatched confirmation', function () {
    runCreateAdmin('admin@example.test', 'a-Strong-pass-2026', 'Another-Pass-2026')->assertFailed();

    expect(User::count())->toBe(0);
});

it('rejects a password that appeared in a data leak', function () {
    $this->fakeLeakedPassword('a-Strong-pass-2026');

    runCreateAdmin('admin@example.test', 'a-Strong-pass-2026')->assertFailed();

    expect(User::count())->toBe(0);
});

it('asks before granting the role to an account already registered with the email, and leaves it when refused', function () {
    $existing = User::factory()->create(['email' => 'admin@example.test']);

    test()->artisan('ejaz:create-admin')
        ->expectsQuestion('البريد الإلكتروني', 'admin@example.test')
        ->expectsConfirmation('للبريد حساب قائم باسم «'.$existing->name.'». أيُمنح دور '.RoleName::TechnicalAdmin->getLabel().'؟', 'no')
        ->assertFailed();

    expect($existing->fresh()->hasRole(RoleName::TechnicalAdmin->value))->toBeFalse();
});

it('creates a read-only super admin on request, and refuses another role', function () {
    test()->artisan('ejaz:create-admin', ['--role' => 'member'])->assertFailed();

    test()->artisan('ejaz:create-admin', ['--role' => 'super_admin'])
        ->expectsQuestion('البريد الإلكتروني', 'super@example.test')
        ->expectsQuestion('الاسم الكامل', 'مشرف عام')
        ->expectsQuestion('كلمة المرور ('.__('account.password_rules.summary').')', 'a-Strong-pass-2026')
        ->expectsQuestion('أعد كتابة كلمة المرور', 'a-Strong-pass-2026')
        ->assertSuccessful();

    expect(User::where('email', 'super@example.test')->firstOrFail()->hasRole(RoleName::SuperAdmin->value))->toBeTrue();
});
