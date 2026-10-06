<?php

use App\Actions\Accounts\SendPhoneVerificationCode;
use App\Models\User;
use Tests\Fakes\FakeSmsGateway;

beforeEach(function () {
    $this->sms = FakeSmsGateway::swap();
    $this->user = User::factory()->create(['phone' => '+966512345678']);
});

function sendCode(User $user): string
{
    app(SendPhoneVerificationCode::class)->handle($user);

    return (string) test()->sms->lastCodeFor((string) $user->phone);
}

it('verifies the phone with the code that was sent', function () {
    $code = sendCode($this->user);

    $this->actingAs($this->user)->post('/account/verify-phone', ['code' => $code])
        ->assertRedirect(route('account.dashboard'));

    expect($this->user->fresh()->hasVerifiedPhone())->toBeTrue();
});

it('accepts the code typed in Arabic-Indic digits', function () {
    $code = sendCode($this->user);
    $arabic = strtr($code, ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']);

    $this->actingAs($this->user)->post('/account/verify-phone', ['code' => $arabic])->assertSessionHasNoErrors();

    expect($this->user->fresh()->hasVerifiedPhone())->toBeTrue();
});

it('rejects a wrong code and invalidates it after five attempts', function () {
    $code = sendCode($this->user);
    $wrong = $code === '111111' ? '222222' : '111111';

    foreach (range(1, 5) as $ignored) {
        $this->actingAs($this->user)->post('/account/verify-phone', ['code' => $wrong])
            ->assertSessionHasErrors(['code' => __('account.phone.invalid')]);
    }

    $this->actingAs($this->user)->post('/account/verify-phone', ['code' => $code])
        ->assertSessionHasErrors(['code' => __('account.phone.expired')]);

    expect($this->user->fresh()->hasVerifiedPhone())->toBeFalse();
});

it('does not accept a code after the phone number changes', function () {
    $code = sendCode($this->user);
    $this->user->forceFill(['phone' => '+966598765432'])->save();

    $this->actingAs($this->user)->post('/account/verify-phone', ['code' => $code])
        ->assertSessionHasErrors('code');
});

it('limits how often a new code can be requested', function () {
    $this->actingAs($this->user)->post('/account/verify-phone/send')->assertSessionHasNoErrors();
    $this->actingAs($this->user)->post('/account/verify-phone/send')->assertSessionHasErrors('code');

    expect($this->sms->sent)->toHaveCount(1);
});

it('keeps the verification page for signed-in users only', function () {
    $this->get('/account/verify-phone')->assertRedirect(route('login'));
});
