<?php

use App\Actions\Users\AnonymizeUser;
use App\Actions\Users\ReleasePhone;
use App\Actions\Users\UpdateUserAccount;
use App\Enums\ActivityEvent;
use App\Enums\RoleName;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use App\Notifications\PhoneNumberReleased;
use Filament\Actions\Action;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\Fakes\FakeSmsGateway;

/*
| مراجعة المشرف على #180 (decisions/2026-11-05-phone-login.md): حجز رقم الغير يُعالج بالرمز حين يُفعَّل التوثيق، وبزر «تحرير
| الجوال» لمديري النظام. والوجهة بعد الدخول «حسابي» إلا رابطي الاستبيان وتوثيق البريد. ومنخفضات «بياناتي» والسباق.
*/

beforeEach(function () {
    $this->sms = FakeSmsGateway::swap();
    Notification::fake();
});

/**
 * @return array<string, string>
 */
function reviewSignUp(array $overrides = []): array
{
    return ['name' => 'صاحب الرقم', 'phone' => '0512345678', 'password' => 'Long-Enough-Pass-2026', 'password_confirmation' => 'Long-Enough-Pass-2026', 'privacy' => '1', ...$overrides];
}

// ── المهمة ١: الرقم المحجوز يأخذه صاحبه بالرمز

it('lets the owner take a number an unverified account holds, by the code, once verification is on', function () {
    enableVerification();
    $squatter = User::factory()->create(['phone' => '+966512345678', 'phone_verified_at' => null]);

    $this->post('/register', reviewSignUp())->assertRedirect(route('account.phone.verify'));
    $owner = User::query()->where('pending_phone', '+966512345678')->sole();
    expect($owner->phone)->toBeNull()->and($squatter->fresh()->phone)->toBe('+966512345678');

    $this->post('/account/verify-phone', ['code' => $this->sms->lastCodeFor('+966512345678')])->assertSessionHasNoErrors()->assertRedirect(route('account.dashboard'));

    expect($owner->fresh())->phone->toBe('+966512345678')->pending_phone->toBeNull()->and($owner->fresh()->hasVerifiedPhone())->toBeTrue()
        ->and($squatter->fresh()->phone)->toBeNull()
        ->and(Activity::query()->where('event', ActivityEvent::PhoneReleased->value)->where('subject_id', $squatter->id)->where('causer_id', $owner->id)->exists())->toBeTrue();
    // للحساب المنزوع منه بريد موثق (الافتراضي في المصنع)، فيُنبَّه
    Notification::assertSentTo($squatter, PhoneNumberReleased::class);
});

it('keeps the number with the holder while the code is wrong, and refuses a number a verified account holds', function () {
    enableVerification();
    $squatter = User::factory()->create(['phone' => '+966512345678', 'phone_verified_at' => null]);

    $this->post('/register', reviewSignUp())->assertRedirect(route('account.phone.verify'));
    $this->post('/account/verify-phone', ['code' => '000000'])->assertSessionHasErrors('code');
    expect($squatter->fresh()->phone)->toBe('+966512345678');

    $this->post('/logout');
    User::factory()->create(['phone' => '+966511111111', 'phone_verified_at' => now()]);
    $this->post('/register', reviewSignUp(['phone' => '0511111111']))->assertSessionHasErrors(['phone' => __('account.register.unavailable')]);
});

it('still refuses a held number while verification is off', function () {
    User::factory()->create(['phone' => '+966512345678', 'phone_verified_at' => null]);

    $this->post('/register', reviewSignUp())->assertSessionHasErrors(['phone' => __('account.register.unavailable')]);
    expect(User::query()->whereNotNull('pending_phone')->exists())->toBeFalse();
});

// ── المهمة ٢: «تحرير الجوال» لمديري النظام

it('lets the system administrators release an unverified number, with a log and a notice', function (RoleName $role) {
    $admin = userWithRole($role);
    $holder = User::factory()->create(['phone' => '+966512345678', 'phone_verified_at' => null]);

    $this->actingAs($admin);
    Livewire::test(EditUser::class, ['record' => $holder->getRouteKey()])
        ->assertActionVisible('releasePhone')
        ->callAction('releasePhone')
        ->assertHasNoActionErrors();

    expect($holder->fresh()->phone)->toBeNull()
        ->and(Activity::query()->where('event', ActivityEvent::PhoneReleased->value)->where('subject_id', $holder->id)->where('causer_id', $admin->id)->exists())->toBeTrue();
    Notification::assertSentTo($holder, PhoneNumberReleased::class);
})->with([RoleName::TechnicalAdmin, RoleName::ExecutiveDirector]);

it('refuses the release to other roles, and for a verified number', function () {
    $holder = User::factory()->create(['phone' => '+966512345678', 'phone_verified_at' => null]);

    foreach ([RoleName::SuperAdmin, RoleName::ContentEditor, RoleName::Member] as $role) {
        expect(fn () => app(ReleasePhone::class)->handle(userWithRole($role), $holder))->toThrow(AuthorizationException::class);
    }

    $verified = User::factory()->create(['phone' => '+966511111111', 'phone_verified_at' => now()]);
    $admin = userWithRole(RoleName::TechnicalAdmin);
    expect(fn () => app(ReleasePhone::class)->handle($admin, $verified))->toThrow(AuthorizationException::class);

    $this->actingAs($admin);
    Livewire::test(EditUser::class, ['record' => $verified->getRouteKey()])->assertActionHidden('releasePhone');

    expect($holder->fresh()->phone)->toBe('+966512345678')->and($verified->fresh()->phone)->toBe('+966511111111');
});

// ── اختبارات ناقصة: الدخول بصيغ الرقم، والاستعادة بلا بريد

it('signs in by the registered phone in any of its forms', function (string $login) {
    $this->post('/register', reviewSignUp())->assertRedirect(route('account.dashboard'));
    $user = User::query()->where('phone', '+966512345678')->sole();
    $this->post('/logout');

    $this->post('/login', ['login' => $login, 'password' => 'Long-Enough-Pass-2026'])->assertRedirect(route('account.dashboard'));
    $this->assertAuthenticatedAs($user);
})->with(['local' => '0512345678', 'international' => '+966512345678', 'arabic digits' => '٠٥١٢٣٤٥٦٧٨']);

it('answers a reset request for a phone-only account neutrally, without sending anything', function () {
    $this->post('/register', reviewSignUp())->assertRedirect(route('account.dashboard'));
    $this->post('/logout');

    // الرقم غير موثق، ولا بريد: الرد نفسه بلا رسالة
    $this->post(route('password.phone.send'), ['phone' => '0512345678'])
        ->assertRedirect(route('password.phone.reset'))->assertSessionHas('status', __('account.password_reset.sent'));
    expect($this->sms->lastCodeFor('+966512345678'))->toBeNull();
    Notification::assertNothingSent();
});

// ── ٦: الاستثناءان بعد الدخول

it('brings the user back to a survey link or an email verification link after login, and nothing else', function () {
    $user = User::factory()->create(['phone' => '+966512345678', 'password' => 'Long-Enough-Pass-2026']);
    $login = fn () => $this->post('/login', ['login' => '0512345678', 'password' => 'Long-Enough-Pass-2026']);

    $survey = route('surveys.show', str_repeat('a', 32));
    $this->withSession(['url.intended' => $survey]);
    $login()->assertRedirect($survey);
    $this->post('/logout');

    $verify = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1((string) $user->email)]);
    $this->withSession(['url.intended' => $verify]);
    $login()->assertRedirect($verify);
    $this->post('/logout');

    $this->withSession(['url.intended' => 'https://evil.example/surveys/'.str_repeat('a', 32)]);
    $login()->assertRedirect(route('account.dashboard'));
});

// ── ٣ و٧: «بياناتي»

it('answers a taken number in the profile with the general message', function () {
    $user = User::factory()->create(['phone' => '+966500000009']);
    User::factory()->create(['phone' => '+966512345678', 'phone_verified_at' => null]);

    $this->actingAs($user)->put('/user/profile-information', ['name' => $user->name, 'email' => $user->email, 'phone' => '0512345678', 'current_password' => 'password'])
        ->assertSessionHasErrors(['phone' => __('account.register.unavailable')], null, 'updateProfileInformation');
});

it('requires the phone in the profile of a self-registered account without a username', function () {
    $this->post('/register', reviewSignUp(['email' => 'self@example.test']))->assertRedirect(route('account.dashboard'));
    $user = User::query()->where('phone', '+966512345678')->sole();

    $this->put('/user/profile-information', ['name' => $user->name, 'email' => 'self@example.test', 'phone' => '', 'current_password' => 'Long-Enough-Pass-2026'])
        ->assertSessionHasErrors('phone', null, 'updateProfileInformation');
    expect($user->fresh()->phone)->toBe('+966512345678');

    // ومن له اسم مستخدم يبقى الجوال له اختيارياً
    $this->post('/logout');
    $old = User::factory()->create(['username' => 'old.user', 'phone' => '+966500000001']);
    $this->actingAs($old)->put('/user/profile-information', ['name' => $old->name, 'email' => $old->email, 'phone' => '', 'current_password' => 'password'])
        ->assertSessionHasNoErrors();
    expect($old->fresh()->phone)->toBeNull();
});

// ── ٤: سباق الحفظ برقم مكرر

it('answers a save racing another account to the same number with a validation error, not a server error', function () {
    $user = User::factory()->create(['phone' => '+966500000009']);
    $admin = userWithRole(RoleName::TechnicalAdmin);
    $race = function () use ($user): void {
        // حساب آخر يأخذ الرقم بعد فحص التحقق وقبل الحفظ
        User::saving(function (User $saving) use ($user): void {
            if ($saving->is($user) && User::query()->where('phone', '+966512345678')->doesntExist()) {
                User::withoutEvents(fn () => User::factory()->create(['phone' => '+966512345678']));
            }
        });
    };

    $race();
    $this->actingAs($user)->put('/user/profile-information', ['name' => $user->name, 'email' => $user->email, 'phone' => '0512345678', 'current_password' => 'password'])
        ->assertSessionHasErrors(['phone' => __('account.register.unavailable')], null, 'updateProfileInformation');

    User::query()->where('phone', '+966512345678')->delete();
    expect(fn () => app(UpdateUserAccount::class)->handle($admin, $user->fresh(), ['name' => $user->name, 'email' => $user->email, 'phone' => '+966512345678']))
        ->toThrow(ValidationException::class);
    expect($user->fresh()->phone)->toBe('+966500000009');

});

// ── إعادة المراجعة (11:00)

it('releases a number from a holder without a verified email without any notice, and warns when the phone is its only identifier', function () {
    $admin = userWithRole(RoleName::TechnicalAdmin);
    $holder = User::factory()->create(['phone' => '+966512345678', 'phone_verified_at' => null, 'username' => null, 'email' => null, 'email_verified_at' => null]);

    $this->actingAs($admin);
    $warns = fn (bool $expected) => fn (Action $action): bool => str_contains((string) $action->getModalDescription(), __('users.release_phone.only_identifier')) === $expected;

    Livewire::test(EditUser::class, ['record' => $holder->getRouteKey()])
        ->assertActionExists('releasePhone', $warns(true))
        ->callAction('releasePhone');

    expect($holder->fresh()->phone)->toBeNull();
    Notification::assertNothingSent();

    // ومن له بريد لا يرى التحذير
    $other = User::factory()->create(['phone' => '+966511111111', 'phone_verified_at' => null]);
    Livewire::test(EditUser::class, ['record' => $other->getRouteKey()])
        ->assertActionExists('releasePhone', $warns(false));
});

it('refuses to release a number the holder verified after the page was opened', function () {
    $admin = userWithRole(RoleName::TechnicalAdmin);
    $holder = User::factory()->create(['phone' => '+966512345678', 'phone_verified_at' => null]);
    $stale = $holder->fresh();

    // وثّقه صاحبه بين فتح الصفحة والتأكيد
    $holder->forceFill(['phone_verified_at' => now()])->save();

    expect(fn () => app(ReleasePhone::class)->handle($admin, $stale))->toThrow(AuthorizationException::class);
    expect($holder->fresh()->phone)->toBe('+966512345678')->and($holder->fresh()->hasVerifiedPhone())->toBeTrue();
});

it('drops the pending number once the account saves a phone of its own, in the profile or by an administrator', function () {
    enableVerification();
    $squatter = User::factory()->create(['phone' => '+966512345678', 'phone_verified_at' => null]);
    $this->post('/register', reviewSignUp(['email' => 'pending@example.test']))->assertRedirect(route('account.phone.verify'));
    $pending = User::query()->where('pending_phone', '+966512345678')->sole();

    // يغيّر رقمه إلى رقم حر: يصير رقمه، ولا يبقى المعلّق ينتزع رقم الحامل برمز قديم
    $this->put('/user/profile-information', ['name' => $pending->name, 'email' => 'pending@example.test', 'phone' => '0511111111', 'current_password' => 'Long-Enough-Pass-2026'])
        ->assertSessionHasNoErrors();
    expect($pending->fresh())->phone->toBe('+966511111111')->pending_phone->toBeNull()
        ->and($squatter->fresh()->phone)->toBe('+966512345678');

    // وكذلك من تعديل المسؤول
    $other = User::factory()->create(['phone' => null]);
    $other->forceFill(['pending_phone' => '+966512345678'])->save();
    app(UpdateUserAccount::class)->handle(userWithRole(RoleName::TechnicalAdmin), $other, ['name' => $other->name, 'email' => $other->email, 'phone' => '+966522222222']);
    expect($other->fresh())->phone->toBe('+966522222222')->pending_phone->toBeNull();
});

it('keeps the cleared numbers only for live accounts: dropped on anonymization and on deletion', function () {
    $user = User::factory()->create(['deletion_requested_at' => now()]);
    $gone = User::factory()->create();
    DB::table('phone_login_cleared_phones')->insert([['user_id' => $user->id, 'phone' => '+966512345671'], ['user_id' => $gone->id, 'phone' => '+966512345672']]);

    app(AnonymizeUser::class)->handle(userWithRole(RoleName::TechnicalAdmin), $user);
    $gone->forceDelete();

    expect(DB::table('phone_login_cleared_phones')->count())->toBe(0);
});

it('puts a phone race on the phone even when the email changes in the same save', function () {
    // نص الاستعلام فيه email ما دام البريد يُحفظ معه، فيُعرف الحقل باسم القيد (المشرف على #180، N1)
    $user = User::factory()->create(['phone' => '+966500000009']);
    $admin = userWithRole(RoleName::TechnicalAdmin);
    User::saving(function (User $saving) use ($user): void {
        if ($saving->is($user) && User::query()->where('phone', '+966512345678')->doesntExist()) {
            User::withoutEvents(fn () => User::factory()->create(['phone' => '+966512345678']));
        }
    });

    $this->actingAs($user)->put('/user/profile-information', ['name' => $user->name, 'email' => 'new@example.test', 'phone' => '0512345678', 'current_password' => 'password'])
        ->assertSessionHasErrors(['phone' => __('account.register.unavailable')], null, 'updateProfileInformation')
        ->assertSessionDoesntHaveErrors('email', null, 'updateProfileInformation');

    try {
        app(UpdateUserAccount::class)->handle($admin, $user->fresh(), ['name' => $user->name, 'email' => 'other@example.test', 'phone' => '+966512345678']);
        $this->fail('expected a validation error');
    } catch (ValidationException $exception) {
        expect(array_keys($exception->errors()))->toBe(['phone']);
    }
});
