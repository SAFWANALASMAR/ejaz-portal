<?php

namespace Database\Factories;

use App\Enums\PaymentEventType;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * تبرع معلّق من متبرع بلا حساب، لم يبدأ عند البوابة بعد.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'purpose' => PaymentPurpose::Donation,
            'amount' => 10000,
            'currency' => 'SAR',
            'status' => PaymentStatus::Pending,
            'gateway' => 'fake',
            'donor_name' => fake()->name(),
            'donor_email' => fake()->unique()->safeEmail(),
        ];
    }

    /**
     * مدفوعة عند البوابة الوهمية، بإيصال وحدث دفع في سجلها.
     */
    public function paid(): static
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
            'receipt_number' => 'R-'.now()->year.'-'.Str::upper(Str::random(8)),
            'gateway_reference' => 'fake_'.Str::random(20),
            'gateway_payment_id' => 'fake_pay_'.Str::random(12),
        ])->afterCreating(function (Payment $payment): void {
            $payment->events()->create([
                'type' => PaymentEventType::Paid,
                'payload' => ['status' => 'paid', 'amount' => $payment->amount, 'currency' => $payment->currency],
            ]);
        });
    }
}
