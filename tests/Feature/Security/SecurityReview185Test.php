<?php

use App\Actions\Accounts\ExportUserData;
use App\Enums\ActivityEvent;
use App\Enums\RoleName;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use App\Notifications\RegistrationAttemptOnAccount;
use App\Support\AccountSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Tests\Fakes\FakeSmsGateway;

/*
| المراجعة الأمنية الشاملة منذ v0.15.1 (البلاغ 185): حجز بريد الغير (I1)، والمنخفضات L1 وL2 وL3 وL5 وL6.
| ورابط فيديو المحكّمين (I2) في tests/Feature/Competitions/ReviewTest.php.
*/

beforeEach(function () {
    $this->sms = FakeSmsGateway::swap();
    Notification::fake();
});

/**
 * @return array<string, string>
 */
function signUp185(array $overrides = []): array
{
    return ['name' => 'مستفيد', 'email' => 'beneficiary@example.test', 'phone' => '0512345678', 'password' => 'Long-Enough-Pass-2026', 'password_confirmation' => 'Long-Enough-Pass-2026', 'privacy' => '1', ...$overrides];
}

// ── I1

it('does not let a phone account holding an unverified email block the owner from registering with it', function () {
    // المهاجم سجّل بجواله وببريد الضحية غير موثق
    User::factory()->create(['username' => null, 'phone' => '+966511111111', 'email' => 'victim@example.test', 'email_verified_at' => null]);

    $this->post('/register', signUp185(['email' => 'victim@example.test']))->assertSessionHasNoErrors()->assertRedirect(route('verification.notice'));
    expect(User::query()->where('email', 'victim@example.test')->count())->toBe(2);
});

it('still keeps the email of an account whose only identifier it is, and a verified email', function (array $holder) {
    User::factory()->create(['email' => 'held@example.test', ...$holder]);

    $this->post('/register', signUp185(['email' => 'held@example.test']))->assertSessionHasErrors(['email' => __('account.register.unavailable')]);
})->with([
    'email only, unverified' => [['username' => null, 'phone' => null, 'email_verified_at' => null]],
    'verified' => [['username' => null, 'phone' => '+966511111111']],
]);

it('signs nobody in by an unverified email two accounts share', function () {
    User::factory()->create(['username' => null, 'phone' => '+966511111111', 'email' => 'shared@example.test', 'email_verified_at' => null, 'password' => 'Long-Enough-Pass-2026']);
    $this->post('/register', signUp185(['email' => 'shared@example.test']))->assertSessionHasNoErrors();
    $this->post('/logout');

    expect(User::findByLoginIdentifier('shared@example.test'))->toBeNull();
    $this->post('/login', ['login' => 'shared@example.test', 'password' => 'Long-Enough-Pass-2026']);
    $this->assertGuest();
});

it('applies the same email rule in the admin user form', function () {
    $admin = userWithRole(RoleName::TechnicalAdmin);
    User::factory()->create(['username' => null, 'phone' => '+966511111111', 'email' => 'victim@example.test', 'email_verified_at' => null]);
    $user = User::factory()->create();

    $this->actingAs($admin);
    Livewire\Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm(['email' => 'victim@example.test'])
        ->call('save')
        ->assertHasNoFormErrors(['email']);
});

// ── L1

it('exports the number the migration cleared, and prunes an abandoned account despite it', function () {
    $user = User::factory()->create();
    DB::table('phone_login_cleared_phones')->insert(['user_id' => $user->id, 'phone' => '+966512345671']);

    expect(app(ExportUserData::class)->handle($user)['account']['cleared_phone'])->toBe('+966512345671');

    enableVerification();
    $abandoned = User::factory()->create(['email_verified_at' => null, 'created_at' => now()->subDays(30), 'last_login_at' => now()->subDays(30)]);
    $abandoned->forceFill(['self_registered_at' => now()->subDays(30)])->save();
    $abandoned->assignRole(RoleName::Member->value);
    DB::table('phone_login_cleared_phones')->insert(['user_id' => $abandoned->id, 'phone' => '+966512345672']);
    AccountSettings::verificationEnabledAt(); // التفعيل قبل التسجيل لا يحمي الحساب

    $this->artisan('ejaz:prune-unverified-accounts')->assertSuccessful();

    expect(User::query()->find($abandoned->id))->toBeNull()
        ->and(DB::table('phone_login_cleared_phones')->where('user_id', $abandoned->id)->exists())->toBeFalse();
});

// ── L2

it('lists before the migration the accounts that would lose their only identifier, without their numbers', function () {
    $migration = require database_path('migrations/2026_11_05_010000_make_phone_unique_for_all_accounts.php');
    $migration->down();

    User::factory()->create(['phone' => '+966512345671', 'phone_verified_at' => now()]);
    $loses = User::factory()->create(['phone' => '+966512345671', 'username' => null, 'email' => null]);
    User::factory()->create(['phone' => '+966512345672', 'username' => null, 'email' => null]);
    $keepsEmail = User::factory()->create(['phone' => '+966512345672']);

    $this->artisan('ejaz:phone-only-duplicates')
        ->expectsOutputToContain((string) $loses->id)
        ->doesntExpectOutputToContain('+96651234567')
        ->assertSuccessful();

    expect(DB::table('users')->where('id', $keepsEmail->id)->value('phone'))->toBe('+966512345672');
    $migration->up();
    $this->artisan('ejaz:phone-only-duplicates')->expectsOutputToContain('لا حساب')->assertSuccessful();
});

// ── L3

it('keeps the pending number out of the activity log values', function () {
    $user = User::factory()->create();
    $user->forceFill(['pending_phone' => '+966512345678'])->save();

    $logged = Activity::query()->where('subject_id', $user->id)->get()->map(fn (Activity $activity) => json_encode($activity->properties))->implode(' ');
    expect($logged)->not->toContain('512345678');
});

// ── L5

it('sends the registration attempt notice to a verified email only', function () {
    $unverified = User::factory()->create(['phone' => '+966512345678', 'email' => 'not-theirs@example.test', 'email_verified_at' => null]);

    $this->post('/register', signUp185())->assertSessionHasErrors('email');
    Notification::assertNotSentTo($unverified, RegistrationAttemptOnAccount::class);

    $verified = User::factory()->create(['phone' => '+966511111111']);
    $this->post('/register', signUp185(['phone' => '0511111111']))->assertSessionHasErrors('email');
    Notification::assertSentTo($verified, RegistrationAttemptOnAccount::class);
});

// ── L6

it('does not let a code take the unverified number of a staff account, and logs it for the administrators', function () {
    enableVerification();
    $staff = userWithRole(RoleName::ContentEditor, ['phone' => '+966512345678', 'phone_verified_at' => null]);

    $this->post('/register', signUp185())->assertRedirect(route('account.phone.verify'));
    $claimant = User::query()->where('pending_phone', '+966512345678')->sole();

    $this->post('/account/verify-phone', ['code' => $this->sms->lastCodeFor('+966512345678')])->assertSessionHasErrors(['code' => __('account.phone.taken')]);

    expect($staff->fresh()->phone)->toBe('+966512345678')
        ->and($claimant->fresh()->phone)->toBeNull()
        ->and(Activity::query()->where('event', ActivityEvent::PhoneClaimRefused->value)->where('subject_id', $staff->id)->where('causer_id', $claimant->id)->exists())->toBeTrue();
});
