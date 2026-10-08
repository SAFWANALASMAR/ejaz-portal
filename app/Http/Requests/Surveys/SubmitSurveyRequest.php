<?php

namespace App\Http\Requests\Surveys;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * إجابة الاستبيان: شكل المدخلات وحدودها. والفحص بأسئلة الاستبيان نفسه في SubmitSurveyResponse.
 */
class SubmitSurveyRequest extends FormRequest
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
            'answers' => ['nullable', 'array', 'max:50'],
            // النوع لكل سؤال (رقم أو نص) يُفحص في SubmitSurveyResponse
            'answers.*' => ['nullable'],
            // رمز التحدي لغير المسجل نص، فلا يُسقط الطلب مدخل بشكل آخر
            'cf-turnstile-response' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
