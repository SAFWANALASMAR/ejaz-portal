<?php

namespace App\Actions\Registers;

use App\Enums\ActivityEvent;
use App\Enums\RegisterEntryType;
use App\Models\RegisterEntry;
use App\Models\User;
use App\Support\PrivateUpload;
use App\Support\StoragePath;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * حفظ مستند في السجلات ذات الصلاحية (المدير التنفيذي والسكرتير — RegisterEntryPolicy). والمسؤول حساب
 * يطّلع على السجلات، والمرفقات PDF حقيقية أو صور يُعاد ترميزها في مجلدها بالقرص الخاص، والمحذوف منها
 * يُحذف ملفه بعد الحفظ. ويُسجَّل الحفظ (LogsActivity). والتجديد تعديل لتاريخ الانتهاء، فتبدأ عتبات
 * التنبيه للتاريخ الجديد (SendRegisterAlerts).
 */
class SaveRegisterEntry
{
    private const MAX_FILES = 10;

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, RegisterEntry $entry, array $data): RegisterEntry
    {
        if (! ($entry->exists ? $actor->can('update', $entry) : $actor->can('create', RegisterEntry::class))) {
            throw new AuthorizationException;
        }

        $validated = Validator::make($data, [
            'type' => ['required', Rule::enum(RegisterEntryType::class)],
            'title' => ['required', 'string', 'max:250'],
            'issuer' => ['nullable', 'string', 'max:200'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'issued_on' => ['required', 'date'],
            'expires_on' => ['nullable', 'date', 'after_or_equal:issued_on'],
            'alert_days' => ['required', 'integer', 'between:0,730'],
            'responsible_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'notes' => ['nullable', 'string', 'max:5000'],
            'attachments' => ['array', 'max:'.self::MAX_FILES],
            'attachments.*.path' => ['required', 'string', 'max:255'],
            'attachments.*.name' => ['required', 'string', 'max:200'],
        ])->validate();

        if (isset($validated['responsible_id'])) {
            $responsible = User::query()->find($validated['responsible_id']);

            if ($responsible === null || ! $responsible->is_active || $responsible->anonymized_at !== null || ! $responsible->can('registers.view')) {
                throw ValidationException::withMessages(['responsible_id' => __('registers.validation.responsible')]);
            }
        }

        $before = collect($entry->attachments ?? [])->keyBy('path');
        $files = array_map(fn (array $file): array => $before->get($file['path']) ?? self::newFile($entry, $file), $validated['attachments'] ?? []);

        DB::transaction(function () use ($actor, $entry, $validated, $files): void {
            $entry->fill([
                'type' => $validated['type'],
                'title' => $validated['title'],
                'issuer' => $validated['issuer'] ?? null,
                'document_number' => $validated['document_number'] ?? null,
                'issued_on' => $validated['issued_on'],
                'expires_on' => $validated['expires_on'] ?? null,
                'alert_days' => (int) $validated['alert_days'],
                'responsible_id' => $validated['responsible_id'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);
            $entry->attachments = $files === [] ? null : array_values($files);

            if (! $entry->exists) {
                $entry->created_by = $actor->id;
            }

            $entry->save();
        });

        // المرفقات لا تدخل سجل النموذج (مسارات)، فيُسجَّل تغيّرها بأسماء الملفات المضافة والمحذوفة
        $added = array_values(array_diff(array_column($files, 'path'), $before->keys()->all()));
        $dropped = $before->keys()->diff(array_column($files, 'path'))->values()->all();

        if ($added !== [] || $dropped !== []) {
            activity('registers')->causedBy($actor)->performedOn($entry)
                ->event(ActivityEvent::Updated->value)
                ->withProperties([
                    'attachments_added' => collect($files)->whereIn('path', $added)->pluck('name')->values()->all(),
                    'attachments_removed' => $before->only($dropped)->pluck('name')->values()->all(),
                ])
                ->log(ActivityEvent::Updated->value);
        }

        $kept = array_column($files, 'path');
        $removed = $before->keys()->reject(fn (string $path): bool => in_array($path, $kept, true) || ! StoragePath::inDirectory($path, RegisterEntry::DIRECTORY));
        Storage::disk(RegisterEntry::disk())->delete($removed->values()->all());

        return $entry;
    }

    /**
     * @param  array{path: string, name: string}  $file
     * @return array{path: string, name: string, size: int}
     *
     * @throws ValidationException
     */
    private static function newFile(RegisterEntry $entry, array $file): array
    {
        $path = $file['path'];

        // مرفق مستند آخر (ولو محذوف ناعماً) لا يُعاد استعماله، فلا يحذفه حذفُه منه
        $inUse = RegisterEntry::withTrashed()
            ->whereJsonContains('attachments', [['path' => $path]])
            ->when($entry->exists, fn ($query) => $query->whereKeyNot($entry->getKey()))
            ->exists();

        if ($inUse) {
            throw ValidationException::withMessages(['attachments' => __('validation.file_in_use')]);
        }

        $size = PrivateUpload::check(RegisterEntry::disk(), RegisterEntry::DIRECTORY, $path, 'attachments', __('registers.validation.invalid_file'))['size'];

        return ['path' => $path, 'name' => str_replace(['/', '\\'], '-', $file['name']), 'size' => $size];
    }
}
