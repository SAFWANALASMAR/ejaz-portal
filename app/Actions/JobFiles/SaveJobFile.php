<?php

namespace App\Actions\JobFiles;

use App\Enums\JobFileIcon;
use App\Enums\RoleName;
use App\Models\JobFile;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * إنشاء ملف وظيفة أو تعديل كل بياناته (job_files.create/update — المدير التنفيذي). والمسؤول والمشاركون من
 * الوظائف الست، والمسؤول لا يُعدّ مشاركاً.
 */
class SaveJobFile
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, JobFile $file, array $data): JobFile
    {
        $allowed = $file->exists ? $actor->can('update', $file) : $actor->can('create', JobFile::class);

        if (! $allowed) {
            throw new AuthorizationException;
        }

        $validated = Validator::make($data, [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'responsible_role' => ['required', 'string', Rule::in(RoleName::values(RoleName::staff()))],
            'participant_roles' => ['present', 'array'],
            'participant_roles.*' => ['string', Rule::in(RoleName::values(RoleName::staff()))],
            'participants_contribute' => ['boolean'],
            'icon' => ['required', Rule::enum(JobFileIcon::class)],
            'sort' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ])->validate();

        $file->fill([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'responsible_role' => $validated['responsible_role'],
            'participant_roles' => self::participants($validated['participant_roles'], (string) $validated['responsible_role']),
            'participants_contribute' => (bool) ($validated['participants_contribute'] ?? false),
            'icon' => $validated['icon'],
            'sort' => (int) ($validated['sort'] ?? 0),
        ]);
        $file->save();

        return $file;
    }

    /**
     * @param  array<int, string>  $roles
     * @return list<string>
     */
    public static function participants(array $roles, string $responsible): array
    {
        return array_values(array_filter(array_unique($roles), fn (string $role): bool => $role !== $responsible));
    }
}
