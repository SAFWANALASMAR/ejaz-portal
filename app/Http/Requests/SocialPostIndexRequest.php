<?php

namespace App\Http\Requests;

use App\Enums\SocialPlatform;
use App\Enums\SocialPostKind;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * التصفية في «منشوراتنا» بالمنصة والنوع من القائمتين المسموحتين. والمدخل الخاطئ يعيد إلى القائمة بلا تصفية.
 */
class SocialPostIndexRequest extends FormRequest
{
    protected $redirectRoute = 'media.social';

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
            'platform' => ['nullable', 'string', Rule::enum(SocialPlatform::class)],
            'kind' => ['nullable', 'string', Rule::enum(SocialPostKind::class)],
            'page' => ListPage::RULES,
        ];
    }
}
