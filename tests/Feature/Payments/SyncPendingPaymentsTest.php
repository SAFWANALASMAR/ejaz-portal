<?php

use App\Contracts\PaymentGateway;
use App\Enums\PaymentEventType;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Payments\FakePaymentGateway;
use Illuminate\Support\Facades\Notification;

it('settles old pending payments from the gateway and closes the ones that never started', function () {
    Notification::fake();
    /** @var FakePaymentGateway $gateway */
    $gateway = app(PaymentGateway::class);

    // دُفعت عند البوابة ولم يصل الإشعار
    $paidAtGateway = Payment::factory()->create();
    $paidAtGateway->forceFill(['gateway_reference' => $gateway->start($paidAtGateway, 'http://localhost/return')->reference])->save();
    $gateway->markPaid((string) $paidAtGateway->gateway_reference);

    // ما زالت مفتوحة عند البوابة
    $open = Payment::factory()->create();
    $open->forceFill(['gateway_reference' => $gateway->start($open, 'http://localhost/return')->reference])->save();

    // لم تبدأ عند البوابة (فشل الاتصال عند البدء)
    $unstarted = Payment::factory()->create();

    // حديثة: لا تُمس
    $recent = Payment::factory()->create();

    Payment::query()->whereKey([$paidAtGateway->id, $open->id, $unstarted->id])->update(['created_at' => now()->subHours(3), 'updated_at' => now()->subHours(3)]);

    $this->artisan('ejaz:sync-pending-payments')->assertSuccessful();

    expect($paidAtGateway->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($paidAtGateway->fresh()->receipt_number)->not->toBeNull()
        ->and($open->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($unstarted->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($unstarted->fresh()->events()->where('type', PaymentEventType::Failed)->count())->toBe(1)
        ->and($recent->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($recent->fresh()->events()->count())->toBe(0);

    Notification::assertCount(1);
});
