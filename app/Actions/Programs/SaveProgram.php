<?php

namespace App\Actions\Programs;

use App\Actions\Content\SaveContent;
use App\Enums\LibraryProgramKind;
use App\Models\LibraryItem;
use App\Models\Program;
use App\Models\ProgramType;
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

        if (isset($check->starts_at) && $check->ends_at !== null && $check->ends_at->lt($check->starts_at)) {
            throw ValidationException::withMessages(['ends_at' => __('programs.errors.ends_before_start')]);
        }

        if ($check->registration_closes_at !== null && $check->registration_opens_at !== null && $check->registration_closes_at->lte($check->registration_opens_at)) {
            throw ValidationException::withMessages(['registration_closes_at' => __('programs.errors.closes_before_opens')]);
        }

        $data = self::withLibraryItem($check, $data);

        return app(SaveContent::class)->handle($actor, $program, $data);
    }

    /**
     * برنامج المكتبة يختار عنصراً منشوراً من قسم نوعه (LibraryProgramKind)، فلا يُرفع مرتين: الكتاب بملف PDF، والصوت والمرئي
     * بمصدر ومدة معروفة في الخادم. وغير برامج المكتبة لا تحمل عنصراً.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private static function withLibraryItem(Program $check, array $data): array
    {
        $type = blank($check->getAttribute('program_type_id')) ? null : ProgramType::query()->find($check->program_type_id, ['id', 'slug']);
        $kind = LibraryProgramKind::tryFrom((string) $type?->slug);

        if ($kind === null) {
            return [...$data, 'library_item_id' => null];
        }

        $item = $check->library_item_id === null ? null : LibraryItem::query()->published()->find($check->library_item_id);
        $problem = match (true) {
            $item === null => 'programs.library.errors.item',
            ! in_array($item->section, $kind->sections(), true) => 'programs.library.errors.section',
            $kind === LibraryProgramKind::Reading && blank($item->file) => 'programs.library.errors.file',
            $kind !== LibraryProgramKind::Reading && (! $item->hasSource() || ! $item->duration_seconds) => 'programs.library.errors.duration',
            default => null,
        };

        if ($problem !== null) {
            throw ValidationException::withMessages(['library_item_id' => __($problem)]);
        }

        return $data;
    }
}
