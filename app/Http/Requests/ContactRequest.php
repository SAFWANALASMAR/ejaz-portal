<?php

namespace App\Http\Requests;

use App\Enums\InquiryType;
use App\Models\Inquiry;
use App\Rules\PlainText;
use App\Support\FormTimer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * نموذج «الاستفسارات والاقتراحات». علامتا الروبوت (الفخ المملوء، والإرسال قبل ثوانٍ) تُفحصان قبل
 * أي تحقق، فيُعطى الروبوت نجاحاً برقم لا يُحفظ ولا يتعلم شيئاً من رسائل الخطأ. أما الختم المعطوب
 * أو المنتهي أو من جلسة أخرى فحال إنسان أطال البقاء، فيُطلب منه تحديث الصفحة.
 */
class ContactRequest extends FormRequest
{
    /**
     * أدنى ثوانٍ لتعبئة النموذج، وأقصاها قبل أن يُطلب تحديث الصفحة.
     */
    public const MIN_SECONDS = 3;

    public const MAX_SECONDS = 7200;

    /**
     * حقل الفخ: اسم بلا دلالة، فلا تملؤه إضافات التعبئة التلقائية في المتصفح.
     */
    public const TRAP = 'contact_extra';

    /**
     * الثواني منذ فتح النموذج، أو null إن كان الختم معطوباً أو من جلسة أخرى.
     */
    private ?int $elapsed = null;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->elapsed = FormTimer::secondsSince($this->input('form_token'), $this->session()->getId());

        if (filled($this->input(self::TRAP)) || ($this->elapsed !== null && $this->elapsed < self::MIN_SECONDS)) {
            throw new HttpResponseException(
                redirect()->route('contact')->with('status', __('contact.form.sent', ['reference' => Inquiry::newReference()])),
            );
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', new PlainText],
            'email' => ['required', 'string', 'email:rfc,strict', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9 -]{7,20}$/'],
            'type' => ['required', Rule::enum(InquiryType::class)],
            'subject' => ['required', 'string', 'max:200', new PlainText],
            'message' => ['required', 'string', 'max:5000', new PlainText(multiline: true)],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->elapsed === null || $this->elapsed > self::MAX_SECONDS) {
                    $validator->errors()->add('form_token', __('contact.form.expired'));
                }
            },
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
