<?php

namespace App\Http\Requests\Account;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * خيار «لا تحفظ قراءاتي» في «بياناتي»: لصاحب الحساب المسجّل نفسه وحده، فلا معرّف في الطلب.
 */
class UpdateReadingTrackingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['disabled' => ['required', 'boolean']];
    }
}
