<?php

namespace App\Console\Commands;

use App\Actions\Payments\RecordPaymentOutcome;
use App\Actions\Payments\SyncPaymentWithGateway;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Support\Payments\GatewayPayment;
use Illuminate\Console\Command;
use Throwable;

/**
 * المعلّق القديم لا يبقى معلّقاً إلى الأبد: ما له مرجع ولم يتحدث منذ مدة يُستعلم عن فاتورته (دُفعت
 * دون أن يصل الإشعار، أو انتهت)، وما لا مرجع له (فشلت البوابة عند البدء) يُعلَّم «فاشلة» بحدث في
 * السجل الثابت. كل عملية على حدة، فلا يوقف خطأ في واحدة الباقي.
 */
class SyncPendingPayments extends Command
{
    protected $signature = 'ejaz:sync-pending-payments';

    protected $description = 'اعتماد حالة عمليات الدفع المعلّقة القديمة من البوابة';

    public function handle(SyncPaymentWithGateway $sync, RecordPaymentOutcome $record): int
    {
        $synced = 0;
        $closed = 0;

        Payment::query()
            ->where('status', PaymentStatus::Pending)
            ->whereNotNull('gateway_reference')
            ->where('updated_at', '<', now()->subMinutes((int) config('payments.pending_sync_minutes')))
            ->lazyById()
            ->each(function (Payment $payment) use ($sync, &$synced): void {
                try {
                    $sync->handle($payment);
                    $synced++;
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
                    $closed++;
                } catch (Throwable $exception) {
                    report($exception);
                }
            });

        $this->info("استُعلم عن {$synced} عملية، وأُغلقت {$closed} لم تبدأ.");

        return self::SUCCESS;
    }
}
