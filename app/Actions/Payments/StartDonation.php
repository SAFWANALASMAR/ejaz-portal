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
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * بدء تبرع: التبرعات مفعّلة من اللوحة، والمبلغ بالريال (بأي أرقام) يُحوَّل إلى هللات ويُفحص بين الحد
 * الأدنى والسقف التقني (config/payments.php)، والبريد للإيصال (من الحساب إن وُجد). وسقف عام للبوابة
 * في الساعة كسقف الرسائل النصية، فلا تُغرق بفواتير معلّقة. يُنشأ السجل معلّقاً بالمبلغ من الخادم، ثم
 * تُنشأ الفاتورة عند البوابة بهذا المبلغ، ويعود رابط صفحة الدفع. وفشل البوابة يُقيَّد حدثاً ويُخبَر
 * به المتبرع، ويعلّم الأمر المجدول السجل «فاشلة» بعد ساعة.
 */
class StartDonation
{
    private const HOURLY_KEY = 'donations:hour';

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
                // بيانات الحساب لا تخضع لقواعد مدخلات الزائر: الاسم حتى 120 حرفاً في الحساب و100 في الإيصال
                'email' => $user !== null ? $user->email : ($input['email'] ?? null),
                'name' => $user !== null ? Str::limit($user->name, 100, '') : ($input['name'] ?? null),
            ],
            [
                'amount' => ['required', 'integer', 'between:'.config('payments.donation.min_halalas').','.config('payments.donation.max_halalas')],
                'email' => ['required', 'string', 'email', 'max:255'],
                'name' => ['nullable', 'string', 'max:100'],
            ],
            ['amount.*' => __('payments.validation.amount', ['min' => number_format(config('payments.donation.min_halalas') / 100, 2), 'max' => number_format(config('payments.donation.max_halalas') / 100)])],
            ['amount' => __('payments.fields.amount'), 'email' => __('payments.fields.email'), 'name' => __('payments.fields.name')],
        )->validate();

        // حد يومي لكل بريد، فلا يُغرق بريد شخص بفواتير وإيصالات، ولا يُنشأ باسمه سجل بعد سجل
        if (RateLimiter::increment('donations:email:'.hash('sha256', strtolower($validated['email'])), 60 * 60 * 24) > (int) config('payments.donation.per_email_per_day')) {
            throw ValidationException::withMessages(['email' => __('payments.donate.email_busy')]);
        }

        // سقف عام للبوابة في الساعة: بلوغه يُبلَّغ (هجوم أو ذروة حقيقية ترفع السقف)
        if (RateLimiter::increment(self::HOURLY_KEY, 3600) > (int) config('payments.donation.per_hour')) {
            report(new RuntimeException('Donation starts reached the hourly cap (DONATIONS_PER_HOUR).'));

            throw ValidationException::withMessages(['amount' => __('payments.donate.busy')]);
        }

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

        try {
            $result = $this->gateway->start($payment, route('payments.return', $payment));
            $url = $result->checkoutUrl ?? throw new RuntimeException('The gateway returned no checkout URL.');

            // مرجع فارغ لا يطابقه إشعار، ورابط دفع لا يُحوَّل إليه إلا بـ https (والبوابة الوهمية محلياً بـ http)
            $scheme = parse_url($url, PHP_URL_SCHEME);

            if ($result->reference === '' || ! ($scheme === 'https' || ($scheme === 'http' && app()->environment('local', 'testing')))) {
                throw new RuntimeException('The gateway returned no usable reference or checkout URL.');
            }
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            report($exception);
            $payment->events()->create(['type' => PaymentEventType::GatewayError, 'payload' => ['message' => $exception::class]]);

            throw ValidationException::withMessages(['amount' => __('payments.donate.gateway_error')]);
        }

        $payment->gateway_reference = $result->reference;
        $payment->save();

        return new Checkout($payment, $url);
    }

    /**
     * المبلغ بالهللات من حقل المبلغ (أرقام عربية أو هندية، وفواصل آلاف، وفاصلة عشرية بأي شكل، وخانتان
     * عشريتان على الأكثر)، أو من المبلغ المقترح المختار إن كان من قائمة اللوحة. وما سواه null فيُرفض.
     *
     * @param  array<string, mixed>  $input
     * @param  list<mixed>  $presets
     */
    private static function halalas(array $input, array $presets): ?int
    {
        $raw = is_scalar($input['amount'] ?? null) ? trim(Digits::latin((string) $input['amount'])) : '';
        // فاصلة الآلاف العربية والمسافات تُحذف، والفاصلة اللاتينية فاصلة آلاف إن تلتها ثلاث خانات بالضبط، وإلا عشرية
        $raw = str_replace(['٬', ' '], '', $raw);
        $raw = (string) preg_replace('/,(?=\d{3}(?!\d))/', '', $raw);
        $raw = str_replace(['٫', ','], '.', $raw);

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
