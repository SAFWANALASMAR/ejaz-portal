<?php

namespace App\Actions\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\PaymentEventType;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Support\Digits;
use App\Support\Payments\Checkout;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * بدء تبرع: التبرعات مفعّلة من اللوحة، والمبلغ بالريال (بأي أرقام) يُحوَّل إلى هللات ويُفحص بين الحد
 * الأدنى والسقف التقني (config/payments.php)، والبريد للإيصال (من الحساب إن وُجد). يُنشأ السجل معلّقاً
 * بالمبلغ من الخادم، ثم تُنشأ العملية عند البوابة بهذا المبلغ، ويعود رابط صفحة الدفع.
 */
class StartDonation
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    /**
     * @param  array<string, mixed>  $input  amount (ريال)، وpreset (مبلغ مقترح من اللوحة)، وemail، وname
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(?User $user, array $input): Checkout
    {
        $settings = Setting::group('donations');

        if (! ($settings['enabled'] ?? false)) {
            throw new AuthorizationException(__('payments.donate.unavailable'));
        }

        $amount = self::halalas($input, (array) ($settings['amounts'] ?? []));

        $validated = Validator::make(
            [
                'amount' => $amount,
                'email' => $user !== null ? $user->email : ($input['email'] ?? null),
                'name' => $user !== null ? $user->name : ($input['name'] ?? null),
            ],
            [
                'amount' => ['required', 'integer', 'between:'.config('payments.donation.min_halalas').','.config('payments.donation.max_halalas')],
                'email' => ['required', 'string', 'email', 'max:255'],
                'name' => ['nullable', 'string', 'max:100'],
            ],
            ['amount.*' => __('payments.validation.amount', ['min' => number_format(config('payments.donation.min_halalas') / 100, 2), 'max' => number_format(config('payments.donation.max_halalas') / 100)])],
            ['amount' => __('payments.fields.amount'), 'email' => __('payments.fields.email'), 'name' => __('payments.fields.name')],
        )->validate();

        $payment = DB::transaction(function () use ($user, $validated): Payment {
            $payment = new Payment;
            $payment->user_id = $user?->id;
            $payment->purpose = PaymentPurpose::Donation;
            $payment->amount = $validated['amount'];
            $payment->currency = (string) config('payments.currency');
            $payment->status = PaymentStatus::Pending;
            $payment->gateway = (string) config('payments.driver');
            $payment->donor_email = $validated['email'];
            $payment->donor_name = $validated['name'] === null ? null : trim($validated['name']);
            $payment->save();

            $payment->events()->create([
                'type' => PaymentEventType::Started,
                'payload' => ['amount' => $payment->amount, 'currency' => $payment->currency],
            ]);

            return $payment;
        });

        $result = $this->gateway->start($payment, route('payments.return', $payment));

        $payment->gateway_reference = $result->reference;
        $payment->save();

        return new Checkout($payment, $result->checkoutUrl ?? throw new RuntimeException('The gateway returned no checkout URL.'));
    }

    /**
     * المبلغ بالهللات من حقل المبلغ (أرقام عربية أو هندية، وفاصلة عشرية بأي شكل، وخانتان عشريتان
     * على الأكثر)، أو من المبلغ المقترح المختار إن كان من قائمة اللوحة. وما سواه null فيُرفض.
     *
     * @param  array<string, mixed>  $input
     * @param  list<mixed>  $presets
     */
    private static function halalas(array $input, array $presets): ?int
    {
        $raw = is_scalar($input['amount'] ?? null) ? trim(str_replace(['٫', ',', '٬'], ['.', '.', ''], Digits::latin((string) $input['amount']))) : '';

        if ($raw === '' && is_scalar($input['preset'] ?? null) && in_array((int) $input['preset'], array_map('intval', $presets), true)) {
            return (int) $input['preset'] * 100;
        }

        if (preg_match('/^\d{1,9}(?:\.\d{1,2})?$/', $raw) !== 1) {
            return null;
        }

        [$riyals, $fraction] = array_pad(explode('.', $raw, 2), 2, '');

        return (int) $riyals * 100 + (int) str_pad($fraction, 2, '0');
    }
}
