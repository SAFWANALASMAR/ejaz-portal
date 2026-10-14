<?php

use App\Actions\Accounts\PruneUnverifiedAccounts;
use App\Actions\Users\CreateStaffUser;
use App\Enums\RoleName;
use App\Models\ProgramRegistration;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Tests\Fakes\FakeSmsGateway;

/*
| حذف الحسابات غير الموثقة (مراجعة التكامل، 14 أكتوبر 2026 — الملاحظة العالية): الحذف نهائي، فلا يُحذف إلا
| حساب تسجيل ذاتي لم يوثّق بريده ولا جواله قط ولم يعد إليه صاحبه، بعد 14 يوماً، ويُعاد فحصه تحت القفل.
*/

beforeEach(function () {
    FakeSmsGateway::swap();
    Notification::fake();
});

/**
 * حساب تسجيل ذاتي متروك: لم يوثَّق شيء فيه، ودخوله الوحيد آلي في طلب التسجيل.
 */
function abandoned(array $attributes = [], int $days = 15): User
{
    $at = now()->subDays($days);
    $user = User::factory()->create([
        'phone' => '+966512345678',
        'email_verified_at' => null,
        'phone_verified_at' => null,
        'created_at' => $at,
        'last_login_at' => $at,
        ...$attributes,
    ]);
    $user->forceFill(['self_registered_at' => $at])->save();
    $user->assignRole(RoleName::Member->value);

    return $user;
}

it('deletes abandoned self-registered accounts after 14 days, and keeps everything else', function () {
    $old = abandoned();
    $cleared = abandoned(['phone' => null]);

    $recent = abandoned(days: 13);
    $verified = abandoned(['phone_verified_at' => now()]);
    $staff = abandoned(days: 30);
    $staff->assignRole(RoleName::SuperAdmin->value);
    $linked = abandoned(days: 30);
    ProgramRegistration::factory()->for($linked)->create();
    $anonymized = abandoned(['anonymized_at' => now()->subDays(20)], days: 30);
    $returned = abandoned(['last_login_at' => now()->subDays(10)], days: 30);

    $this->artisan('ejaz:prune-unverified-accounts')->assertSuccessful();

    expect(User::find($old->id))->toBeNull()
        ->and(User::find($cleared->id))->toBeNull()
        ->and(User::whereKey([$recent->id, $verified->id, $staff->id, $linked->id, $anonymized->id, $returned->id])->count())->toBe(6);
});

it('deletes an account registered through the site and never verified, once the period has passed', function () {
    $this->post('/register', [
        'name' => 'عبدالله محمد',
        'email' => 'left@example.test',
        'phone' => '0512345678',
        'password' => 'long-enough-pass-2026',
        'password_confirmation' => 'long-enough-pass-2026',
        'privacy' => '1',
    ])->assertSessionHasNoErrors();

    $user = User::where('email', 'left@example.test')->firstOrFail();

    // الدخول الآلي بعد التسجيل لا يُعد عودة
    expect($user->self_registered_at)->not->toBeNull()
        ->and($user->last_login_at)->not->toBeNull();

    $this->travel(13)->days();
    expect(app(PruneUnverifiedAccounts::class)->handle())->toBe(0);

    $this->travel(2)->days();
    expect(app(PruneUnverifiedAccounts::class)->handle())->toBe(1)
        ->and(User::find($user->id))->toBeNull();
});

it('never deletes a long-verified member who changed their phone and has not entered the new code', function () {
    $member = abandoned(['email_verified_at' => null, 'phone_verified_at' => now()->subDays(60), 'last_login_at' => null], days: 90);

    $this->actingAs($member)->put('/user/profile-information', [
        'name' => $member->name,
        'email' => $member->email,
        'phone' => '0598765432',
        'current_password' => 'password',
    ])->assertSessionHasNoErrors();

    $member->refresh();
    expect($member->phone_verified_at)->toBeNull()
        ->and($member->first_verified_at)->not->toBeNull();

    $this->travel(30)->days();
    expect(app(PruneUnverifiedAccounts::class)->handle())->toBe(0)
        ->and(User::find($member->id))->not->toBeNull();
});

it('never deletes an account an administrator created, even without a phone or a verified email', function () {
    $admin = User::factory()->create();
    $admin->assignRole(RoleName::SuperAdmin->value);

    $created = app(CreateStaffUser::class)->handle($admin, ['name' => 'موظف جديد', 'email' => 'new.staff@example.test'], []);
    // ولو فقد توثيق بريده: لم ينشئه التسجيل الذاتي
    $created->forceFill(['email_verified_at' => null, 'first_verified_at' => null])->save();

    $this->travel(30)->days();
    expect(app(PruneUnverifiedAccounts::class)->handle())->toBe(0)
        ->and(User::find($created->id))->not->toBeNull();
});

it('re-checks each account under its row lock, so one verified after being picked is kept', function () {
    $user = abandoned();
    $action = app(PruneUnverifiedAccounts::class);

    expect($action->query()->pluck('id')->all())->toBe([$user->id]);

    // وثّق جواله بين الاختيار والحذف
    $user->forceFill(['phone_verified_at' => now()])->save();

    expect($action->prune($user->id))->toBeFalse()
        ->and(User::find($user->id))->not->toBeNull();
});

it('keeps an account a record points to by an unchecked key, and carries on with the rest', function () {
    $linked = abandoned();
    ProgramRegistration::factory()->for($linked)->create();
    $free = abandoned(['phone' => null]);
    $action = app(PruneUnverifiedAccounts::class);

    // بلا فحص المفاتيح: المفتاح الأجنبي يمنع الحذف، فيبقى الحساب بلا خطأ يوقف الأمر
    expect($action->prune($linked->id, []))->toBeFalse()
        ->and(User::find($linked->id))->not->toBeNull()
        ->and($action->prune($free->id, []))->toBeTrue();
});
