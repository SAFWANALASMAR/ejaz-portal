<?php

namespace App\Actions\Documents;

use App\Enums\DocumentFieldType;
use App\Enums\FormAudience;
use App\Enums\FormEntryStatus;
use App\Models\FormEntry;
use App\Models\FormTemplate;
use App\Models\FormTemplateVersion;
use App\Models\User;
use App\Support\DocumentFields;
use App\Support\PrivatePdf;
use App\Support\SignatureImage;
use App\Support\StoragePath;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * تعبئة نموذج إلكتروني أو تعديل المعبأ ما دام مسودة (السكرتير — FormEntryPolicy). المعبأ الجديد على
 * الإصدار الحالي للنموذج المفعّل، والمعدَّل على إصداره. الإجابات بأنواع حقولها، والمرفق PDF حقيقي في مجلده،
 * والتوقيع صورة PNG يُعاد ترميزها. والملف المستبدل يُحذف بعد اعتماد الحفظ. ويُربط المعبأ بصاحبه من جمهور
 * النموذج فيُحفظ في ملفه (requirements/12 البند 10).
 */
class SaveFormEntry
{
    /**
     * @param  array<string, mixed>  $data  answers، وfiles ({key: {path, name}})، وsignatures ({key: صورة base64})، وperson_id
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, FormEntry $entry, array $data, ?FormTemplate $template = null): FormEntry
    {
        $version = $this->version($actor, $entry, $template);
        $fields = $version->typedFields();

        $validated = Validator::make($data, [
            ...DocumentFields::rules($fields),
            'person_id' => ['nullable', 'integer'],
        ], [], [...DocumentFields::attributes($fields), 'person_id' => __('documents.fields.person')])->validate();

        // صاحب المعبأ من جمهور النموذج. ومن لا يُرسَل صاحبه في التعديل يبقى على صاحبه
        $personId = array_key_exists('person_id', $data) ? self::person($version, $validated['person_id'] ?? null, $entry->person_id) : $entry->person_id;

        $answers = [];
        $before = collect($entry->files ?? [])->keyBy('key');
        $files = [];

        foreach ($fields as $field) {
            $key = $field['key'];

            if ($field['type']->isLayout()) {
                continue;
            }

            if ($field['type'] === DocumentFieldType::Table) {
                $rows = self::tableRows($field, (array) ($validated['answers'][$key] ?? []));

                if ($rows !== []) {
                    $answers[$key] = $rows;
                } elseif ($field['required']) {
                    throw ValidationException::withMessages(['answers.'.$key => __('validation.required', ['attribute' => $field['label']])]);
                }
            } elseif ($field['type'] === DocumentFieldType::File) {
                $given = $validated['files'][$key] ?? null;

                if (is_array($given)) {
                    $kept = $before->get($key);
                    $files[] = $kept !== null && $kept['path'] === $given['path'] ? $kept : self::newFile($entry, $key, $given);
                }
            } elseif ($field['type'] !== DocumentFieldType::Signature && filled($validated['answers'][$key] ?? null)) {
                $answers[$key] = trim((string) $validated['answers'][$key]);
            }
        }

        // التواقيع تُحفظ صوراً جديدة، وتُحذف إن فشل الحفظ بعدها. والسابقة تُحذف بعد اعتماده
        $signatures = [];
        $previousSignatures = $entry->signatures ?? [];

        try {
            foreach ($fields as $field) {
                $given = $validated['signatures'][$field['key']] ?? null;

                if ($field['type'] === DocumentFieldType::Signature && filled($given)) {
                    $signatures[] = ['key' => $field['key'], 'path' => SignatureImage::store((string) $given, FormEntry::disk(), FormEntry::SIGNATURES_DIRECTORY, 'signatures.'.$field['key'])];
                }
            }

            DB::transaction(function () use ($actor, $entry, $version, $answers, $files, $signatures, $personId): void {
                if ($entry->exists) {
                    /** @var FormEntry $locked */
                    $locked = FormEntry::query()->whereKey($entry->id)->lockForUpdate()->firstOrFail();

                    // اعتُمد بين فتح الصفحة والحفظ
                    if (! $actor->can('update', $locked)) {
                        throw new AuthorizationException;
                    }
                } else {
                    $entry->form_template_version_id = $version->id;
                    $entry->status = FormEntryStatus::Draft;
                    $entry->created_by = $actor->id;
                }

                $entry->forceFill([
                    'person_id' => $personId,
                    'answers' => $answers === [] ? null : $answers,
                    'files' => $files === [] ? null : $files,
                    'signatures' => $signatures === [] ? null : $signatures,
                ])->save();
            });
        } catch (Throwable $exception) {
            Storage::disk(FormEntry::disk())->delete(array_column($signatures, 'path'));

            throw $exception;
        }

        // المستبدل والمحذوف من الملفات، والتواقيع السابقة كلها (حُفظت من جديد)
        $kept = array_column($files, 'path');
        $removed = $before->pluck('path')->reject(fn (string $path): bool => in_array($path, $kept, true) || ! StoragePath::inDirectory($path, FormEntry::FILES_DIRECTORY));
        Storage::disk(FormEntry::disk())->delete([...$removed->values()->all(), ...array_column($previousSignatures, 'path')]);

        return $entry;
    }

    /**
     * @throws AuthorizationException
     */
    private function version(User $actor, FormEntry $entry, ?FormTemplate $template): FormTemplateVersion
    {
        if ($entry->exists) {
            if (! $actor->can('update', $entry)) {
                throw new AuthorizationException;
            }

            return $entry->version;
        }

        if ($template === null || ! $template->is_active || ! $actor->can('create', FormEntry::class)) {
            throw new AuthorizationException;
        }

        return $template->currentVersion ?? throw new AuthorizationException;
    }

    /**
     * صاحب المعبأ من جمهور النموذج (FormAudience::people). وصاحبه الحالي يبقى ولو تغيّر حسابه بعد الربط.
     *
     * @throws ValidationException
     */
    private static function person(FormTemplateVersion $version, mixed $id, ?int $current): ?int
    {
        if ($id === null || (int) $id === $current) {
            return $id === null ? null : $current;
        }

        if (! $version->template->audience->people()->whereKey((int) $id)->exists()) {
            throw ValidationException::withMessages(['person_id' => __('documents.validation.invalid_person')]);
        }

        return (int) $id;
    }

    /**
     * صفوف الجدول بأعمدته، والصف الفارغ كله يُسقط.
     *
     * @param  array{columns: list<string>}  $field
     * @param  array<mixed>  $given
     * @return list<array<string, string>>
     */
    private static function tableRows(array $field, array $given): array
    {
        $rows = [];

        foreach ($given as $row) {
            $cells = [];

            foreach (DocumentFields::columnKeys($field) as $column) {
                $cells[$column] = trim((string) (((array) $row)[$column] ?? ''));
            }

            if (implode('', $cells) !== '') {
                $rows[] = $cells;
            }
        }

        return $rows;
    }

    /**
     * @param  array{path: string, name: string}  $file
     * @return array{key: string, path: string, name: string, size: int}
     *
     * @throws ValidationException
     */
    private static function newFile(FormEntry $entry, string $key, array $file): array
    {
        // ملف معبأ آخر (ولو محذوف ناعماً) لا يُعاد استعماله، فلا يحذفه استبدالُه فيه
        $inUse = FormEntry::withTrashed()
            ->whereJsonContains('files', [['path' => $file['path']]])
            ->when($entry->exists, fn ($query) => $query->whereKeyNot($entry->getKey()))
            ->exists();

        if ($inUse) {
            throw ValidationException::withMessages(['files.'.$key => __('validation.file_in_use')]);
        }

        return [
            'key' => $key,
            'path' => $file['path'],
            'name' => str_replace(['/', '\\'], '-', $file['name']),
            'size' => PrivatePdf::check(FormEntry::disk(), FormEntry::FILES_DIRECTORY, $file['path'], 'files.'.$key),
        ];
    }
}
