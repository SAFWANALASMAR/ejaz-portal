<?php

namespace App\Actions\Services;

use App\Enums\FormFieldType;
use App\Models\Opening;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * حفظ فرصة من اللوحة: الصلاحية، ثم فحص حقول النموذج (معرّفات صالحة لا تتكرر، وخيارات لحقل
 * الاختيار)، ثم الحفظ. والفرصة تُسجَّل بنفسها (LogsActivity).
 */
class SaveOpening
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, Opening $opening, array $data): Opening
    {
        $allowed = $opening->exists ? $actor->can('update', $opening) : $actor->can('create', Opening::class);

        if (! $allowed) {
            throw new AuthorizationException;
        }

        $opening->fill($data);
        $opening->form_fields = self::cleanFields($opening->form_fields ?? []);

        DB::transaction(fn () => $opening->save());

        return $opening;
    }

    /**
     * @param  array<mixed>  $fields
     * @return list<array{key: string, label: string, type: string, required: bool, options?: list<string>}>
     *
     * @throws ValidationException
     */
    private static function cleanFields(array $fields): array
    {
        $clean = [];

        foreach (array_values($fields) as $field) {
            $key = is_array($field) ? (string) ($field['key'] ?? '') : '';
            $type = is_array($field) ? FormFieldType::tryFrom((string) ($field['type'] ?? '')) : null;
            $label = is_array($field) ? trim((string) ($field['label'] ?? '')) : '';

            if (preg_match('/^[a-z][a-z0-9_]{0,39}$/', $key) !== 1 || $type === null || $label === '' || isset($clean[$key])) {
                throw ValidationException::withMessages(['form_fields' => __('services.openings.invalid_fields')]);
            }

            $options = array_values(array_unique(array_filter(array_map(fn (mixed $option): string => trim((string) $option), (array) ($field['options'] ?? [])))));

            if ($type === FormFieldType::Select && $options === []) {
                throw ValidationException::withMessages(['form_fields' => __('services.openings.invalid_fields')]);
            }

            $clean[$key] = [
                'key' => $key,
                'label' => mb_substr($label, 0, 150),
                'type' => $type->value,
                'required' => (bool) ($field['required'] ?? false),
                ...($type === FormFieldType::Select ? ['options' => $options] : []),
            ];
        }

        return array_values($clean);
    }
}
