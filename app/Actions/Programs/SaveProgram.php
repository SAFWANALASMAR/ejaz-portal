<?php

namespace App\Actions\Programs;

use App\Actions\Content\SaveContent;
use App\Models\Program;
use App\Models\User;
use App\Rules\SafeUrl;
use App\Support\FormFieldRules;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * حفظ برنامج من اللوحة: حقول التسجيل الإضافية تُنظَّف (بلا ملفات)، والمواعيد متسقة، ثم الحفظ
 * عبر SaveContent (الصلاحية، وتنظيف الصورة، والسجل).
 */
class SaveProgram
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, Program $program, array $data): Program
    {
        if (array_key_exists('form_fields', $data)) {
            $data['form_fields'] = FormFieldRules::clean((array) ($data['form_fields'] ?? []), allowFiles: false);
        }

        $check = (clone $program)->fill($data);

        if (filled($check->meeting_url) && ! SafeUrl::isSafe($check->meeting_url, allowInternal: false)) {
            throw ValidationException::withMessages(['meeting_url' => __('validation.url', ['attribute' => __('programs.admin.fields.meeting_url')])]);
        }

        if ($check->ends_at !== null && $check->ends_at->lt($check->starts_at)) {
            throw ValidationException::withMessages(['ends_at' => __('programs.errors.ends_before_start')]);
        }

        if ($check->registration_closes_at !== null && $check->registration_opens_at !== null && $check->registration_closes_at->lte($check->registration_opens_at)) {
            throw ValidationException::withMessages(['registration_closes_at' => __('programs.errors.closes_before_opens')]);
        }

        return app(SaveContent::class)->handle($actor, $program, $data);
    }
}
