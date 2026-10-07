<?php

namespace App\Support\Payments;

use App\Enums\PaymentStatus;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * قراءة كائنات ميسر (الفاتورة، والدفعة، والإشعار) إلى حالة موحدة. والبوابة الوهمية تقبل الإشعار
 * بالصيغة نفسها، فيبقى مسار الإشعار واحداً.
 */
final class MoyasarPayload
{
    /**
     * أقصى طول للمعرّفات المخزنة (أعمدة بطول 100)؛ وما تجاوزه مرفوض لا مقصوص.
     */
    public const MAX_ID = 100;

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
     * دفعة (payment) واحدة: مرجعها فاتورتها إن وُجدت، ورسالتها من مصدرها. ولا يُقرأ من المصدر غير الرسالة.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromPayment(array $data, ?string $reference = null): GatewayPayment
    {
        $source = is_array($data['source'] ?? null) ? $data['source'] : [];
        $message = $source['message'] ?? $data['message'] ?? null;

        return new GatewayPayment(
            reference: $reference ?? self::id($data['invoice_id'] ?? null) ?? (string) ($data['id'] ?? ''),
            status: self::status(self::string($data['status'] ?? null)),
            amount: (int) ($data['amount'] ?? 0),
            currency: strtoupper((string) ($data['currency'] ?? '')),
            paymentId: self::id($data['id'] ?? null),
            message: self::string($message),
            attempt: true,
            refunded: self::amount($data['refunded'] ?? null),
        );
    }

    /**
     * فاتورة (invoice) بدفعاتها: الحالة من الفاتورة، ورقم الدفعة ورسالتها من الدفعة التي بحالة الفاتورة
     * نفسها (المدفوعة لفاتورة مدفوعة، فبها يُسترد المبلغ)، وإلا آخر دفعة محسومة، وإلا آخر محاولة؛
     * لأن ترتيب الدفعات في رد البوابة غير مضمون.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromInvoice(array $data): GatewayPayment
    {
        $status = self::status(self::string($data['status'] ?? null));
        $payments = array_values(array_filter((array) ($data['payments'] ?? []), 'is_array'));
        $statusOf = fn (array $payment): PaymentStatus => self::status(self::string($payment['status'] ?? null));
        $matching = array_values(array_filter($payments, fn (array $payment): bool => $statusOf($payment) === $status));
        $settled = array_values(array_filter($payments, fn (array $payment): bool => $statusOf($payment) !== PaymentStatus::Pending));
        $last = ($matching !== [] ? end($matching) : ($settled !== [] ? end($settled) : ($payments !== [] ? end($payments) : null))) ?: null;
        $source = is_array($last['source'] ?? null) ? $last['source'] : [];

        return new GatewayPayment(
            reference: (string) ($data['id'] ?? ''),
            status: $status,
            amount: (int) ($data['amount'] ?? 0),
            currency: strtoupper((string) ($data['currency'] ?? '')),
            paymentId: self::id($last['id'] ?? null),
            message: self::string($source['message'] ?? null),
            checkoutUrl: self::string($data['url'] ?? null),
            refunded: self::amount($last['refunded'] ?? null),
        );
    }

    /**
     * الإشعار من جسم الطلب وحده (لا من سلسلة الاستعلام): رمزه السري بمقارنة ثابتة الزمن مع المضبوط
     * (ولا قبول بلا رمز مضبوط) وإلا null فيُرفض ويُقيَّد أمنياً. ثم نوعه حدث دفع، ومعرّفه بطول مقبول،
     * وبياناته دفعة، وإلا فالشكل غير مقبول (استثناء) لا توقيع خاطئ.
     *
     * @throws InvalidArgumentException إشعار بتوقيع صحيح وشكل غير مقبول
     */
    public static function webhook(Request $request, string $secret): ?GatewayEvent
    {
        $body = $request->isJson() ? (array) $request->json()->all() : $request->post();

        $token = $body['secret_token'] ?? null;
        $type = $body['type'] ?? null;
        $id = $body['id'] ?? null;
        $data = $body['data'] ?? null;

        if ($secret === '' || ! is_string($token) || ! hash_equals($secret, $token)) {
            return null;
        }

        if (! is_string($type) || ! str_starts_with($type, 'payment_') || self::id($id) === null || ! is_array($data)) {
            throw new InvalidArgumentException('Malformed payment webhook.');
        }

        /** @var array<string, mixed> $data */
        return new GatewayEvent((string) $id, self::fromPayment($data));
    }

    private static function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * مبلغ بالهللات إن ذكرته البوابة (المسترد مثلاً)، وإلا null.
     */
    private static function amount(mixed $value): ?int
    {
        return is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : null;
    }

    /**
     * معرّف يصلح للتخزين: نص غير فارغ لا يتجاوز طول العمود.
     */
    private static function id(mixed $value): ?string
    {
        return is_string($value) && $value !== '' && strlen($value) <= self::MAX_ID ? $value : null;
    }
}
