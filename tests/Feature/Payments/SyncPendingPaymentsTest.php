<?php

use App\Actions\Payments\SyncPaymentWithGateway;
use App\Contracts\PaymentGateway;
use App\Enums\PaymentEventType;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Payments\FakePaymentGateway;
use Illuminate\Support\Facades\Notification;
use Mockery\MockInterface;

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

it('pays a failed invoice the gateway later reports paid, scrubs donors from old failed records, and fails when a sync blows up', function () {
    Notification::fake();
    /** @var FakePaymentGateway $gateway */
    $gateway = app(PaymentGateway::class);

    // فاشلة في عمر فاتورتها، ثم دُفعت عند البوابة
    $failed = Payment::factory()->create();
    $failed->forceFill(['gateway_reference' => $gateway->start($failed, 'http://localhost/return')->reference])->save();
    $gateway->markFailed((string) $failed->gateway_reference);
    app(SyncPaymentWithGateway::class)->handle($failed);
    expect($failed->fresh()->status)->toBe(PaymentStatus::Failed);
    $gateway->markPaid((string) $failed->gateway_reference);

    // فاشلة قديمة بهوية متبرع
    $old = Payment::factory()->create(['status' => PaymentStatus::Failed, 'donor_email' => 'old@example.test']);

    Payment::query()->whereKey($failed->id)->update(['updated_at' => now()->subHours(2)]);
    Payment::query()->whereKey($old->id)->update(['created_at' => now()->subDays(40), 'updated_at' => now()->subDays(40)]);

    $this->artisan('ejaz:sync-pending-payments')->assertSuccessful();

    expect($failed->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($failed->fresh()->receipt_number)->not->toBeNull()
        ->and($old->fresh()->donor_email)->toBeNull()
        ->and($old->fresh()->donor_name)->toBeNull();
    Notification::assertCount(1);

    // تعذر غير متوقع في عملية: يُبلَّغ ويعود الأمر بالفشل ليلتقطه الرصد، ولا يوقف الباقي
    $broken = Payment::factory()->create();
    $broken->forceFill(['gateway_reference' => 'fake_broken'])->save();
    Payment::query()->whereKey($broken->id)->update(['updated_at' => now()->subHours(2)]);
    $this->mock(PaymentGateway::class, fn (MockInterface $mock) => $mock->shouldReceive('fetch')->once()->andThrow(new LogicException('boom')));

    $this->artisan('ejaz:sync-pending-payments')->assertFailed();
});
