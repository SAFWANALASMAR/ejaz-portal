<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * رقم الصفحة في مكتبة الصور. والمدخل الخاطئ يعيد إلى الصفحة الأولى.
 */
class AlbumIndexRequest extends FormRequest
{
    protected $redirectRoute = 'media.albums.index';

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
            'page' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];
    }
}
