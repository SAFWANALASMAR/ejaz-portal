<?php

namespace App\Support\Payments;

use App\Enums\PaymentStatus;
use Illuminate\Http\Request;

/**
 * قراءة كائنات ميسر (الفاتورة، والدفعة، والإشعار) إلى حالة موحدة. والبوابة الوهمية تقبل الإشعار
 * بالصيغة نفسها، فيبقى مسار الإشعار واحداً.
 */
final class MoyasarPayload
{
    /**
     * حالات ميسر الكثيرة مقابَلة بأربع: ما لم يُحسم معلّق، والمحصَّل مدفوع، وما سواه فاشل أو مسترد.
     */
    public static function status(?string $status): PaymentStatus
    {
        return match ($status) {
            'paid', 'captured' => PaymentStatus::Paid,
            'refunded' => PaymentStatus::Refunded,
            'failed', 'voided', 'expired', 'canceled', 'cancelled', 'abandoned' => PaymentStatus::Failed,
            default => PaymentStatus::Pending,
        };
    }

    /**
     * دفعة (payment): مرجعها فاتورتها إن وُجدت، ورسالتها من مصدرها. ولا يُقرأ من المصدر غير الرسالة.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromPayment(array $data, ?string $reference = null): GatewayPayment
    {
        $source = is_array($data['source'] ?? null) ? $data['source'] : [];
        $message = $source['message'] ?? $data['message'] ?? null;

        return new GatewayPayment(
            reference: $reference ?? self::string($data['invoice_id'] ?? null) ?? (string) ($data['id'] ?? ''),
            status: self::status(self::string($data['status'] ?? null)),
            amount: (int) ($data['amount'] ?? 0),
            currency: strtoupper((string) ($data['currency'] ?? '')),
            paymentId: self::string($data['id'] ?? null),
            message: self::string($message),
        );
    }

    /**
     * فاتورة (invoice) بدفعاتها: الحالة من الفاتورة، ورقم الدفعة ورسالتها من آخر دفعة محصَّلة أو
     * آخر محاولة.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromInvoice(array $data): GatewayPayment
    {
        $payments = array_values(array_filter((array) ($data['payments'] ?? []), 'is_array'));
        $settled = array_values(array_filter($payments, fn (array $payment): bool => self::status(self::string($payment['status'] ?? null)) !== PaymentStatus::Pending));
        $last = ($settled !== [] ? end($settled) : ($payments !== [] ? end($payments) : null)) ?: null;
        $source = is_array($last['source'] ?? null) ? $last['source'] : [];

        return new GatewayPayment(
            reference: (string) ($data['id'] ?? ''),
            status: self::status(self::string($data['status'] ?? null)),
            amount: (int) ($data['amount'] ?? 0),
            currency: strtoupper((string) ($data['currency'] ?? '')),
            paymentId: self::string($last['id'] ?? null),
            message: self::string($source['message'] ?? null),
            checkoutUrl: self::string($data['url'] ?? null),
        );
    }

    /**
     * الإشعار: رمزه السري بمقارنة ثابتة الزمن مع المضبوط (ولا قبول بلا رمز مضبوط)، ونوعه حدث دفع،
     * وبياناته دفعة. وما سوى ذلك null فيُرفض.
     */
    public static function webhook(Request $request, string $secret): ?GatewayEvent
    {
        $token = $request->input('secret_token');
        $type = $request->input('type');
        $id = $request->input('id');
        $data = $request->input('data');

        if ($secret === '' || ! is_string($token) || ! hash_equals($secret, $token)) {
            return null;
        }

        if (! is_string($type) || ! str_starts_with($type, 'payment_') || ! is_string($id) || $id === '' || ! is_array($data)) {
            return null;
        }

        /** @var array<string, mixed> $data */
        return new GatewayEvent($id, self::fromPayment($data));
    }

    private static function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
