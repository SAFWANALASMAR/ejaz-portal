<?php

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Testing\PendingCommand;

function runCreateAdmin(string $email, string $password, ?string $confirmation = null): PendingCommand
{
    return test()->artisan('ejaz:create-admin')
        ->expectsQuestion('الاسم الكامل', 'مشرف البوابة')
        ->expectsQuestion('البريد الإلكتروني', $email)
        ->expectsQuestion('كلمة المرور ('.__('account.password_rules.summary').')', $password)
        ->expectsQuestion('أعد كتابة كلمة المرور', $confirmation ?? $password);
}

it('creates a verified super admin', function () {
    runCreateAdmin('Admin@Example.test', 'a-Strong-pass-2026')->assertSuccessful();

    $admin = User::where('email', 'admin@example.test')->firstOrFail();

    expect($admin->hasRole(RoleName::SuperAdmin->value))->toBeTrue()
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

it('rejects an email that is already registered', function () {
    User::factory()->create(['email' => 'admin@example.test']);

    runCreateAdmin('admin@example.test', 'a-Strong-pass-2026')->assertFailed();
});
