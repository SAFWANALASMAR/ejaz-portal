<?php

namespace App\Http\Requests\Services;

use App\Models\Opening;
use App\Models\ServiceRequest;
use App\Support\FormFieldRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * نموذج الطلب: الإجابات والمرفقات بقواعد حقول الفرصة، والنية: حفظ مسودة أو إرسال.
 * الفرصة من رابط التقديم، أو من الطلب نفسه عند تعديله، وتعديله لصاحبه فقط.
 */
class SaveServiceRequestRequest extends FormRequest
{
    private ?Opening $opening = null;

    public function authorize(): bool
    {
        $existing = $this->existing();

        return $existing === null || ($this->user()?->can('edit', $existing) ?? false);
    }

    /**
     * طلب غيره يُعامل كأنه غير موجود، كصفحته (ServiceRequestController). وطلبه في حالة لا
     * تُعدَّل يُرفض بـ 403.
     */
    protected function failedAuthorization(): void
    {
        abort_if($this->existing()?->user_id !== $this->user()?->id, 404);

        parent::failedAuthorization();
    }

    public function submitting(): bool
    {
        return $this->input('intent') === 'submit';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'intent' => ['required', 'in:save,submit'],
            ...FormFieldRules::for($this->opening(), $this->submitting(), $this->existing()->files ?? []),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return FormFieldRules::attributes($this->opening());
    }

    public function existing(): ?ServiceRequest
    {
        $request = $this->route('serviceRequest');

        return $request instanceof ServiceRequest ? $request : null;
    }

    public function opening(): Opening
    {
        $existing = $this->existing();

        if ($existing !== null) {
            return $existing->opening;
        }

        return $this->opening ??= Opening::query()->published()->where('slug', (string) $this->route('slug'))->firstOrFail();
    }
}
