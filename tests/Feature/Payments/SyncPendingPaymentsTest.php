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
        // لم تبدأ عند البوابة: ليست سجلاً مالياً فعلياً، فتُمسح هوية المتبرع منها
        ->and($unstarted->fresh()->donor_email)->toBeNull()
        ->and($recent->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($recent->fresh()->events()->count())->toBe(0);

    Notification::assertCount(1);
});

it('closes a pending payment the gateway no longer knows after the stale window, resends missing receipts, and leaves other drivers alone', function () {
    Notification::fake();

    // مرجع لا تعرفه البوابة (فاتورة حُذفت، أو بقايا مشغّل سابق)
    $unknown = Payment::factory()->create();
    $unknown->forceFill(['gateway_reference' => 'fake_unknown'])->save();

    // مشغّل آخر: لا يُمس
    $other = Payment::factory()->create(['gateway' => 'moyasar']);
    $other->forceFill(['gateway_reference' => 'inv_other'])->save();

    // مدفوعة قبل ساعة لم يُدفع إيصالها إلى الطابور (وقت الدفع لا يُعدَّل بعد الحفظ، فيُضبط عند الإنشاء)
    $paid = Payment::factory()->paid()->create(['receipt_sent_at' => null, 'paid_at' => now()->subHour()]);

    Payment::query()->whereKey([$unknown->id, $other->id])->update(['created_at' => now()->subHours(72), 'updated_at' => now()->subHours(72)]);

    $this->artisan('ejaz:sync-pending-payments')->assertSuccessful();

    expect($unknown->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($unknown->fresh()->events()->where('type', PaymentEventType::Failed)->count())->toBe(1)
        ->and($other->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($paid->fresh()->receipt_sent_at)->not->toBeNull();

    Notification::assertCount(1);
});
