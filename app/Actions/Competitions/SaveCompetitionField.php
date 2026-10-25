<?php

namespace App\Actions\Competitions;

use App\Enums\CompetitionFieldType;
use App\Models\Competition;
use App\Models\CompetitionField;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * حفظ حقل تسجيل مخصص في مسابقة الطلاب (قرار المدير التنفيذي: حقول التسجيل قابلة للإضافة من اللوحة بلا
 * برمجة): نص، أو رقم، أو اختيار بخياراته (اثنان فأكثر). ويُضاف ويُعدَّل في أي وقت، وما سُجّل قبله يبقى.
 */
class SaveCompetitionField
{
    /**
     * @param  array<string, mixed>  $data  {label, type, options?: list<string>, is_required, sort_order?}
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, Competition $competition, ?CompetitionField $field, array $data): CompetitionField
    {
        if (! $actor->can('update', $competition) || ! $competition->student_mode || ($field !== null && $field->competition_id !== $competition->id)) {
            throw new AuthorizationException;
        }

        $select = ($data['type'] ?? null) === CompetitionFieldType::Select->value;

        $validated = Validator::make($data, [
            'label' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::enum(CompetitionFieldType::class)],
            'options' => [$select ? 'required' : 'nullable', 'array', $select ? 'min:2' : 'max:100', 'max:100'],
            'options.*' => ['string', 'max:150', 'distinct'],
            'is_required' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ], attributes: __('competitions.student.field_attributes'))->validate();

        $field ??= new CompetitionField;
        $field->competition_id = $competition->id;
        $field->fill([
            'label' => trim((string) $validated['label']),
            'type' => CompetitionFieldType::from((string) $validated['type']),
            'options' => $select ? array_values(array_map(fn ($option): string => trim((string) $option), (array) $validated['options'])) : null,
            'is_required' => (bool) ($validated['is_required'] ?? false),
            'sort_order' => (int) ($validated['sort_order'] ?? ($field->exists ? $field->sort_order : $competition->fields()->max('sort_order') + 1)),
        ])->save();

        return $field;
    }
}
