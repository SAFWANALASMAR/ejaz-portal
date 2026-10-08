<?php

namespace App\Console\Commands;

use App\Actions\Payments\RecordPaymentOutcome;
use App\Actions\Payments\SendPaymentReceipt;
use App\Actions\Payments\SyncPaymentWithGateway;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Support\Payments\GatewayPayment;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * المعلّق القديم لا يبقى معلّقاً إلى الأبد، على مشغّل البوابة الحالي وحده:
 * - ما له مرجع ولم يُستعلم عنه منذ مدة يُستعلم عن فاتورته (دُفعت دون أن يصل الإشعار، أو انتهت)، ومعه
 *   «الفاشلة» التي ما زالت فاتورتها في عمرها، لأن البوابة قد تقبل دفعها بعد فشل محاولة.
 * - ما بقي معلّقاً بعد انتهاء الفاتورة بهامش (ولو تعذر الاستعلام) يُعلَّم «فاشلة» بحدث يوضح السبب.
 * - ما لا مرجع له (فشلت البوابة عند البدء) يُعلَّم «فاشلة» وتُمسح هوية المتبرع منه، فليس سجلاً مالياً فعلياً.
 * - و«الفاشلة» القديمة تُمسح منها هوية المتبرع بعد مدة (config/payments.php)، إذ لا إيصال لها.
 * - والمدفوع الذي لم يُدفع إيصاله إلى الطابور يُعاد إرساله.
 * كل عملية على حدة، فلا يوقف خطأ في واحدة الباقي، ويعود الأمر بالفشل إن تعذرت أي واحدة ليلتقطه الرصد.
 */
class SyncPendingPayments extends Command
{
    protected $signature = 'ejaz:sync-pending-payments';

    protected $description = 'اعتماد حالة عمليات الدفع المعلّقة القديمة من البوابة، وإعادة إرسال ما لم يُرسل من الإيصالات';

    private int $failures = 0;

    public function handle(SyncPaymentWithGateway $sync, RecordPaymentOutcome $record, SendPaymentReceipt $receipt): int
    {
        $driver = (string) config('payments.driver');
        $syncedAgo = now()->subMinutes((int) config('payments.pending_sync_minutes'));
        $stale = now()->subHours((int) config('payments.stale_hours'));
        $synced = 0;
        $closed = 0;
        $receipts = 0;

        // معلّقة بمرجع، أو فاشلة في عمر فاتورتها
        Payment::query()
            ->where('gateway', $driver)
            ->whereNotNull('gateway_reference')
            ->where('updated_at', '<', $syncedAgo)
            ->where(fn (Builder $query) => $query
                ->where('status', PaymentStatus::Pending)
                ->orWhere(fn (Builder $query) => $query->where('status', PaymentStatus::Failed)->where('created_at', '>', $stale)))
            ->lazyById()
            ->each(function (Payment $payment) use ($sync, $record, $stale, &$synced, &$closed): void {
                $this->attempt(function () use ($sync, $record, $stale, $payment, &$synced, &$closed): void {
                    $payment = $sync->handle($payment, evenIfFailed: true);
                    $synced++;

                    if ($payment->status === PaymentStatus::Pending && $payment->created_at?->lt($stale)) {
                        $record->handle($payment, new GatewayPayment((string) $payment->gateway_reference, PaymentStatus::Failed, $payment->amount, $payment->currency, message: 'no result from the gateway after the invoice expired'));
                        $closed++;
                    }

                    // يُلمس بعد كل استعلام، فيُحترم فاصل الاستعلام ولو لم تتغير الحالة
                    $payment->fresh()?->touch();
                });
            });

        Payment::query()
            ->where('status', PaymentStatus::Pending)
            ->whereNull('gateway_reference')
            ->where('created_at', '<', now()->subHours((int) config('payments.unstarted_hours')))
            ->lazyById()
            ->each(function (Payment $payment) use ($record, &$closed): void {
                $this->attempt(function () use ($record, $payment, &$closed): void {
                    $record->handle($payment, new GatewayPayment('', PaymentStatus::Failed, $payment->amount, $payment->currency, message: 'not started at the gateway'));
                    $payment->fresh()?->forceFill(['donor_name' => null, 'donor_email' => null])->save();
                    $closed++;
                });
            });

        // الفاشلة القديمة: لا إيصال لها، فلا تبقى هوية المتبرع فيها
        Payment::query()
            ->where('status', PaymentStatus::Failed)
            ->where('created_at', '<', now()->subDays((int) config('payments.failed_donor_days')))
            ->where(fn (Builder $query) => $query->whereNotNull('donor_name')->orWhereNotNull('donor_email'))
            ->lazyById()
            ->each(function (Payment $payment): void {
                $this->attempt(fn () => $payment->forceFill(['donor_name' => null, 'donor_email' => null])->save());
            });

        Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->whereNotNull('donor_email')
            ->whereNull('receipt_sent_at')
            ->where('paid_at', '<', now()->subMinutes(10))
            ->lazyById()
            ->each(function (Payment $payment) use ($receipt, &$receipts): void {
                $this->attempt(function () use ($receipt, $payment, &$receipts): void {
                    $receipt->handle($payment);
                    $receipts++;
                });
            });

        $this->info("استُعلم عن {$synced} عملية، وأُغلقت {$closed}، وأُعيد إرسال {$receipts} إيصالاً، وتعذرت {$this->failures}.");

        return $this->failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function attempt(callable $work): void
    {
        try {
            $work();
        } catch (Throwable $exception) {
            report($exception);
            $this->failures++;
        }
    }
}
