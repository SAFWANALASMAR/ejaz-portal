<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * سؤال للمساعد: نص بطول أقصى، ومسار الصفحة الحالية للزائر (بلا استعلام) اختيارياً.
 */
class AskAssistantRequest extends FormRequest
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
            'question' => ['required', 'string', 'min:2', 'max:'.(int) config('assistant.max_question')],
            'page' => ['nullable', 'string', 'max:300'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['question' => __('assistant.widget.question'), 'page' => __('assistant.widget.page')];
    }
}
