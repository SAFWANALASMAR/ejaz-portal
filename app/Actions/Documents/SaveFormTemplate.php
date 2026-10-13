<?php

namespace App\Actions\Documents;

use App\Enums\FormAudience;
use App\Models\FormEntry;
use App\Models\FormTemplate;
use App\Models\FormTemplateVersion;
use App\Models\User;
use App\Support\DocumentFields;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * حفظ نموذج إلكتروني (السكرتير). الحقول بإصداراتها: حقول مختلفة عن الإصدار الحالي تنشئ إصداراً جديداً
 * يصير الحالي، والمعبأ على إصدار سابق يبقى عليه بحقوله (requirements/05).
 */
class SaveFormTemplate
{
    /**
     * @param  array<string, mixed>  $data  title، وdescription، وis_active، وfields
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, FormTemplate $template, array $data): FormTemplate
    {
        $allowed = $template->exists ? $actor->can('update', $template) : $actor->can('create', FormTemplate::class);

        if (! $allowed) {
            throw new AuthorizationException;
        }

        $validated = Validator::make($data, [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
            'audience' => ['nullable', Rule::enum(FormAudience::class)],
            'fields' => ['required', 'array'],
        ])->validate();

        $fields = DocumentFields::clean($validated['fields']);

        // الجمهور يحدد ملفات من رُبط بها المعبأ ومن يراه، فلا يتغير بعد أول معبأ
        $audience = isset($validated['audience']) ? FormAudience::from(self::value($validated['audience'])) : null;

        if ($template->exists && $audience !== null && $audience !== $template->audience
            && FormEntry::withTrashed()->whereIn('form_template_version_id', $template->versions()->select('id'))->exists()) {
            throw ValidationException::withMessages(['audience' => __('documents.validation.audience_locked')]);
        }

        DB::transaction(function () use ($actor, $template, $validated, $fields, $audience): void {
            $template->fill([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'is_active' => (bool) ($validated['is_active'] ?? true),
                'audience' => $audience ?? $template->audience,
            ]);
            $template->save();

            /** @var FormTemplate $locked */
            $locked = FormTemplate::query()->whereKey($template->id)->lockForUpdate()->firstOrFail();
            $current = $locked->currentVersion;

            // ترتيب المفاتيح داخل كل حقل لا يهم (jsonb يرتبها)، وترتيب الحقول يهم
            if ($current !== null && $current->fields == $fields) {
                return;
            }

            $version = new FormTemplateVersion;
            $version->form_template_id = $locked->id;
            $version->version = ((int) $locked->versions()->max('version')) + 1;
            $version->fields = $fields;
            $version->created_by = $actor->id;
            $version->save();

            $locked->forceFill(['current_version_id' => $version->id])->save();
            $template->setRawAttributes($locked->getAttributes(), true);
        });

        return $template;
    }

    /**
     * من اللوحة يصل التعداد نفسه، ومن غيرها قيمته.
     */
    private static function value(mixed $audience): string
    {
        return $audience instanceof FormAudience ? $audience->value : (string) $audience;
    }
}
