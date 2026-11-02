<?php

namespace App\Http\Requests\Achievements;

use App\Http\Requests\ListPage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * صفحة «إنجازاتنا» العامة: رقم الصفحة وحده، والمدخل الخاطئ يعيد إلى أولها.
 */
class AchievementsRequest extends FormRequest
{
    protected $redirectRoute = 'achievements.index';

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
            'page' => ListPage::RULES,
        ];
    }
}
