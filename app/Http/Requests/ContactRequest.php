<?php

namespace App\Http\Requests;

use App\Enums\InquiryType;
use App\Rules\PlainText;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * نموذج «الاستفسارات والاقتراحات». والفخ (website) وختم الوقت (form_token) يفحصهما المتحكم،
 * لأن الروبوت يُعطى نجاحاً لا خطأ.
 */
class ContactRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150', new PlainText],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9 -]{7,20}$/'],
            'type' => ['required', Rule::enum(InquiryType::class)],
            'subject' => ['required', 'string', 'max:200', new PlainText],
            'message' => ['required', 'string', 'max:5000', new PlainText(multiline: true)],
            'form_token' => ['required', 'string'],
            'website' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return collect(['name', 'email', 'phone', 'type', 'subject', 'message'])
            ->mapWithKeys(fn (string $field): array => [$field => __('contact.form.'.$field)])
            ->all();
    }
}
