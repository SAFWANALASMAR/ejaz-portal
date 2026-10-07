<?php

namespace App\Http\Requests\Payments;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * نموذج التبرع: شكل المدخلات وحدودها. والمبلغ والبريد يُفحصان في StartDonation.
 */
class StartDonationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['nullable', 'string', 'max:20'],
            'preset' => ['nullable', 'integer'],
            'email' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'amount' => __('payments.fields.amount'),
            'email' => __('payments.fields.email'),
            'name' => __('payments.fields.name'),
        ];
    }
}
