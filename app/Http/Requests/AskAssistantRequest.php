<?php

namespace App\Http\Requests;

use App\Rules\PlainText;
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
            // نص صالح بلا محارف تحكم (ومنها NUL الذي ترفضه القاعدة بعد استدعاء المزود)، والسؤال يقبل الأسطر
            'question' => ['required', 'string', 'min:2', 'max:'.(int) config('assistant.max_question'), new PlainText(multiline: true)],
            'page' => ['nullable', 'string', 'max:300', new PlainText],
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
