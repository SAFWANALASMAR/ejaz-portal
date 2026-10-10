<?php

namespace App\Http\Requests\Programs;

use App\Models\Program;
use App\Support\FormFieldRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * التسجيل في برنامج: إجابات حقوله الإضافية بقواعدها، والإلزامي منها مطلوب.
 */
class RegisterForProgramRequest extends FormRequest
{
    private ?Program $program = null;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return FormFieldRules::for($this->program(), submitting: true);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return FormFieldRules::attributes($this->program());
    }

    public function program(): Program
    {
        return $this->program ??= Program::query()->published()->where('slug', (string) $this->route('slug'))->firstOrFail();
    }
}
