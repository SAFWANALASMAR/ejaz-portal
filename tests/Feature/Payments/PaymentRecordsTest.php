<?php

use App\Actions\Accounts\ExportUserData;
use App\Actions\Payments\RefundPayment;
use App\Actions\Payments\SyncPaymentWithGateway;
use App\Actions\Users\AnonymizeUser;
use App\Contracts\PaymentGateway;
use App\Enums\ActivityEvent;
use App\Enums\PaymentEventType;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payments\FakePaymentGateway;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Mockery\MockInterface;
use Spatie\Activitylog\Models\Activity;

it('shows a member their own payments only', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $mine = Payment::factory()->paid()->for($user)->create(['donor_name' => 'صاحب الحساب']);
    Payment::factory()->paid()->for($other)->create(['donor_name' => 'شخص آخر']);

    $this->actingAs($user)->get('/account/payments')->assertOk()
        ->assertSee($mine->receipt_number)
        ->assertDontSee('شخص آخر');

    $this->actingAs($user)->get('/account')->assertSee(__('payments.account.nav'));
    auth()->logout();
    $this->get('/account/payments')->assertRedirect(route('login'));
});

it('opens the payments screen to the board and the super admin, and keeps others out', function () {
    $payment = Payment::factory()->paid()->create();

    $this->actingAs(userWithRole(RoleName::BoardMember))->get(PaymentResource::getUrl('index'))->assertOk();
    $this->actingAs(userWithRole(RoleName::BoardMember))->get(PaymentResource::getUrl('view', ['record' => $payment]))->assertOk()->assertSee($payment->receipt_number);
    $this->actingAs(userWithRole(RoleName::SuperAdmin))->get(PaymentResource::getUrl('view', ['record' => $payment]))->assertOk();

    foreach ([RoleName::ContentEditor, RoleName::Secretary, RoleName::ProgramManager, RoleName::Reviewer] as $role) {
        $this->actingAs(userWithRole($role))->get(PaymentResource::getUrl('index'))->assertForbidden();
    }

    $member = User::factory()->create();
    expect($member->can('view', $payment))->toBeFalse()
        ->and(userWithRole(RoleName::BoardMember)->can('refund', $payment))->toBeFalse();
});

it('lets no one delete or edit a payment, not even the super admin', function () {
    $payment = Payment::factory()->paid()->create();
    $admin = userWithRole(RoleName::SuperAdmin);

    expect($admin->can('delete', $payment))->toBeFalse()
        ->and($admin->can('update', $payment))->toBeFalse()
        ->and($admin->can('create', Payment::class))->toBeFalse()
        ->and($admin->can('forceDelete', $payment))->toBeFalse()
        ->and($admin->can('refund', $payment))->toBeTrue();
});

it('refunds a paid payment through the gateway for the super admin with a reason, and logs it', function () {
    $payment = Payment::factory()->create();
    /** @var FakePaymentGateway $gateway */
    $gateway = app(PaymentGateway::class);
    $payment->forceFill(['gateway_reference' => $gateway->start($payment, 'http://localhost/return')->reference])->save();
    $gateway->markPaid((string) $payment->gateway_reference);
    app(SyncPaymentWithGateway::class)->handle($payment->fresh());
    $admin = userWithRole(RoleName::SuperAdmin);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid);

    expect(fn () => app(RefundPayment::class)->handle(userWithRole(RoleName::BoardMember), $payment->fresh(), 'سبب الاسترداد الكامل'))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => app(RefundPayment::class)->handle($admin, $payment->fresh(), 'قصير'))
        ->toThrow(ValidationException::class);

    $refunded = app(RefundPayment::class)->handle($admin, $payment->fresh(), 'طلب المتبرع الاسترداد خلال المدة');

    expect($refunded->status)->toBe(PaymentStatus::Refunded)
        ->and($refunded->refund_reason)->toBe('طلب المتبرع الاسترداد خلال المدة')
        ->and($refunded->refunded_by)->toBe($admin->id)
        ->and($refunded->refunded_at)->not->toBeNull()
        ->and($gateway->fetch($refunded->gateway_reference)->status)->toBe(PaymentStatus::Refunded)
        ->and($refunded->events()->where('type', PaymentEventType::Refunded)->count())->toBe(1)
        ->and(Activity::where('event', ActivityEvent::PaymentRefunded->value)->where('subject_id', $payment->id)->exists())->toBeTrue();

    // لا استرداد لما ليس مدفوعاً، ولا مرتين
    expect(fn () => app(RefundPayment::class)->handle($admin, $refunded, 'طلب المتبرع الاسترداد خلال المدة'))
        ->toThrow(ValidationException::class)
        ->and(fn () => app(RefundPayment::class)->handle($admin, Payment::factory()->create(), 'طلب المتبرع الاسترداد خلال المدة'))
        ->toThrow(ValidationException::class);
});

it('keeps the amount and the ledger immutable, in the model and in the database', function () {
    $payment = Payment::factory()->paid()->create();
    $event = $payment->events()->firstOrFail();
    $amount = $payment->amount;

    expect(fn () => $payment->forceFill(['amount' => 1])->save())->toThrow(LogicException::class)
        ->and(fn () => $payment->fresh()->forceFill(['currency' => 'USD'])->save())->toThrow(LogicException::class)
        ->and(fn () => $payment->fresh()->forceFill(['purpose' => 'program_fee'])->save())->toThrow(LogicException::class)
        ->and(fn () => $payment->fresh()->delete())->toThrow(LogicException::class)
        ->and(fn () => $event->update(['type' => 'failed']))->toThrow(LogicException::class)
        ->and(fn () => $event->delete())->toThrow(LogicException::class)
        // الاستعلام الخام يرده مشغّل القاعدة. وكل محاولة في نقطة حفظ، فلا يُبطل الخطأ معاملة الاختبار
        ->and(fn () => DB::transaction(fn () => PaymentEvent::query()->whereKey($event->id)->update(['type' => 'failed'])))->toThrow(QueryException::class)
        ->and(fn () => DB::transaction(fn () => PaymentEvent::query()->whereKey($event->id)->delete()))->toThrow(QueryException::class)
        ->and(fn () => DB::transaction(fn () => Payment::query()->whereKey($payment->id)->delete()))->toThrow(QueryException::class)
        ->and(fn () => DB::transaction(fn () => Payment::query()->whereKey($payment->id)->update(['amount' => 1])))->toThrow(QueryException::class)
        // المكتملة لا تعود معلّقة، وإيصالها لا يتغير
        ->and(fn () => DB::transaction(fn () => Payment::query()->whereKey($payment->id)->update(['status' => 'pending'])))->toThrow(QueryException::class)
        ->and(fn () => DB::transaction(fn () => Payment::query()->whereKey($payment->id)->update(['receipt_number' => 'R-0000-FORGED00'])))->toThrow(QueryException::class);

    expect($payment->fresh()->amount)->toBe($amount);
});

it('shows the donor identity only to those allowed, not to the board', function () {
    $payment = Payment::factory()->paid()->create(['donor_name' => 'اسم المتبرع', 'donor_email' => 'donor-private@example.test']);

    $this->actingAs(userWithRole(RoleName::BoardMember))->get(PaymentResource::getUrl('view', ['record' => $payment]))->assertOk()
        ->assertSee($payment->receipt_number)
        ->assertDontSee('donor-private@example.test')
        ->assertDontSee('اسم المتبرع');
    $this->actingAs(userWithRole(RoleName::SuperAdmin))->get(PaymentResource::getUrl('view', ['record' => $payment]))->assertOk()
        ->assertSee('donor-private@example.test');
});

it('keeps the payment paid without a refund reason when the gateway refuses the refund, and records the failure', function () {
    $payment = Payment::factory()->paid()->create();
    $admin = userWithRole(RoleName::SuperAdmin);
    $this->mock(PaymentGateway::class, fn (MockInterface $mock) => $mock->shouldReceive('refund')->once()->andThrow(new ConnectionException('gateway down')));

    expect(fn () => app(RefundPayment::class)->handle($admin, $payment, 'طلب المتبرع الاسترداد خلال المدة'))->toThrow(ValidationException::class);

    $payment->refresh();

    expect($payment->status)->toBe(PaymentStatus::Paid)
        ->and($payment->refund_reason)->toBeNull()
        ->and($payment->refunded_by)->toBeNull()
        ->and($payment->events()->where('type', PaymentEventType::RefundFailed)->count())->toBe(1);
});

it('refunds from the panel after confirming the password, and hides the action from the board', function () {
    $payment = Payment::factory()->create();
    /** @var FakePaymentGateway $gateway */
    $gateway = app(PaymentGateway::class);
    $payment->forceFill(['gateway_reference' => $gateway->start($payment, 'http://localhost/return')->reference])->save();
    $gateway->markPaid((string) $payment->gateway_reference);
    app(SyncPaymentWithGateway::class)->handle($payment->fresh());

    $this->actingAs(userWithRole(RoleName::BoardMember));
    Livewire::test(ViewPayment::class, ['record' => $payment->getRouteKey()])->assertActionHidden('refund');

    $this->actingAs(userWithRole(RoleName::SuperAdmin));
    Livewire::test(ViewPayment::class, ['record' => $payment->getRouteKey()])
        ->callAction('refund', data: ['reason' => 'طلب المتبرع الاسترداد خلال المدة', 'password' => 'wrong-password'])
        ->assertHasActionErrors(['password']);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid);

    Livewire::test(ViewPayment::class, ['record' => $payment->getRouteKey()])
        ->callAction('refund', data: ['reason' => 'طلب المتبرع الاسترداد خلال المدة', 'password' => 'password'])
        ->assertHasNoActionErrors();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Refunded);
});

it('scrubs the donor identity from the payments of an anonymised account and keeps the ledger', function () {
    $user = User::factory()->create(['deletion_requested_at' => now()]);
    $payment = Payment::factory()->paid()->for($user)->create();

    app(AnonymizeUser::class)->handle(userWithRole(RoleName::SuperAdmin), $user);

    $payment->refresh();

    expect($payment->donor_name)->toBeNull()
        ->and($payment->donor_email)->toBeNull()
        ->and($payment->user_id)->toBe($user->id)
        ->and($payment->amount)->toBe(10000)
        ->and($payment->receipt_number)->not->toBeNull()
        ->and($payment->status)->toBe(PaymentStatus::Paid);
});

it('hides the donation links when donations are disabled from the panel', function () {
    Setting::put('donations', ['enabled' => false]);
    $failed = Payment::factory()->create(['status' => PaymentStatus::Failed]);

    // نص التلميح يذكر «المحاولة مرة أخرى» أيضاً، فالفحص على الرابط نفسه
    $this->actingAs(User::factory()->create())->get('/account/payments')->assertOk()->assertDontSee(route('donate'));
    $this->get(route('payments.return', $failed))->assertOk()->assertDontSee(route('donate'));

    Setting::put('donations', ['enabled' => true]);
    $this->get(route('payments.return', $failed))->assertOk()->assertSee(route('donate'));
});

it('includes a member’s payments in their data export', function () {
    $user = User::factory()->create();
    $payment = Payment::factory()->paid()->for($user)->create();

    $export = app(ExportUserData::class)->handle($user);

    expect(json_encode($export))->toContain($payment->receipt_number);
});
