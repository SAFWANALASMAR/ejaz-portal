<?php

namespace App\Console\Commands;

use App\Actions\Payments\RecordPaymentOutcome;
use App\Actions\Payments\SendPaymentReceipt;
use App\Actions\Payments\SyncPaymentWithGateway;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Support\Payments\GatewayPayment;
use Illuminate\Console\Command;
use Throwable;

/**
 * المعلّق القديم لا يبقى معلّقاً إلى الأبد، على مشغّل البوابة الحالي وحده:
 * - ما له مرجع ولم يتحدث منذ مدة يُستعلم عن فاتورته (دُفعت دون أن يصل الإشعار، أو انتهت).
 * - ما بقي معلّقاً بعد انتهاء الفاتورة بهامش (ولو تعذر الاستعلام) يُعلَّم «فاشلة» بحدث يوضح السبب.
 * - ما لا مرجع له (فشلت البوابة عند البدء) يُعلَّم «فاشلة» وتُمسح هوية المتبرع منه، فليس سجلاً مالياً فعلياً.
 * - والمدفوع الذي لم يُدفع إيصاله إلى الطابور يُعاد إرساله.
 * كل عملية على حدة، فلا يوقف خطأ في واحدة الباقي.
 */
class SyncPendingPayments extends Command
{
    protected $signature = 'ejaz:sync-pending-payments';

    protected $description = 'اعتماد حالة عمليات الدفع المعلّقة القديمة من البوابة، وإعادة إرسال ما لم يُرسل من الإيصالات';

    public function handle(SyncPaymentWithGateway $sync, RecordPaymentOutcome $record, SendPaymentReceipt $receipt): int
    {
        $driver = (string) config('payments.driver');
        $synced = 0;
        $closed = 0;
        $receipts = 0;

        Payment::query()
            ->where('status', PaymentStatus::Pending)
            ->where('gateway', $driver)
            ->whereNotNull('gateway_reference')
            ->where('updated_at', '<', now()->subMinutes((int) config('payments.pending_sync_minutes')))
            ->lazyById()
            ->each(function (Payment $payment) use ($sync, $record, &$synced, &$closed): void {
                try {
                    $payment = $sync->handle($payment);
                    $synced++;

                    if ($payment->status === PaymentStatus::Pending && $payment->created_at?->lt(now()->subHours((int) config('payments.stale_hours')))) {
                        $record->handle($payment, new GatewayPayment((string) $payment->gateway_reference, PaymentStatus::Failed, $payment->amount, $payment->currency, message: 'no result from the gateway after the invoice expired'));
                        $closed++;
                    }
                } catch (Throwable $exception) {
                    report($exception);
                }
            });

        Payment::query()
            ->where('status', PaymentStatus::Pending)
            ->whereNull('gateway_reference')
            ->where('created_at', '<', now()->subHours((int) config('payments.unstarted_hours')))
            ->lazyById()
            ->each(function (Payment $payment) use ($record, &$closed): void {
                try {
                    $record->handle($payment, new GatewayPayment('', PaymentStatus::Failed, $payment->amount, $payment->currency, message: 'not started at the gateway'));
                    $payment->fresh()?->forceFill(['donor_name' => null, 'donor_email' => null])->save();
                    $closed++;
                } catch (Throwable $exception) {
                    report($exception);
                }
            });

        Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->whereNotNull('donor_email')
            ->whereNull('receipt_sent_at')
            ->where('paid_at', '<', now()->subMinutes(10))
            ->lazyById()
            ->each(function (Payment $payment) use ($receipt, &$receipts): void {
                try {
                    $receipt->handle($payment);
                    $receipts++;
                } catch (Throwable $exception) {
                    report($exception);
                }
            });

        $this->info("استُعلم عن {$synced} عملية، وأُغلقت {$closed}، وأُعيد إرسال {$receipts} إيصالاً.");

        return self::SUCCESS;
    }
}
