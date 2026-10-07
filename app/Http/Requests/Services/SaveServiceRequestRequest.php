<?php

namespace App\Http\Requests\Services;

use App\Models\Opening;
use App\Models\ServiceRequest;
use App\Support\OpeningFormRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * نموذج الطلب: الإجابات والمرفقات بقواعد حقول الفرصة، والنية: حفظ مسودة أو إرسال.
 * الفرصة من رابط التقديم، أو من الطلب نفسه عند تعديله، وتعديله لصاحبه فقط.
 */
class SaveServiceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $existing = $this->existing();

        return $existing === null || ($this->user()?->can('edit', $existing) ?? false);
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
            ...OpeningFormRules::for($this->opening(), $this->submitting(), $this->existing()),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return OpeningFormRules::attributes($this->opening());
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

        return Opening::query()->published()->where('slug', (string) $this->route('slug'))->firstOrFail();
    }
}
