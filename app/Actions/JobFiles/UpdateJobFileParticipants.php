<?php

namespace App\Actions\JobFiles;

use App\Enums\RoleName;
use App\Models\JobFile;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * المسؤول عن الملف يحدد مشاركيه من الوظائف الست، وهل يضيفون مستندات (JobFilePolicy::manageParticipants).
 * وبه يُحدَّد «أصحاب الاختصاص» و«المختص» في المصفوفة لكل ملف.
 */
class UpdateJobFileParticipants
{
    /**
     * @param  array<string, mixed>  $data  participant_roles، وparticipants_contribute
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, JobFile $file, array $data): JobFile
    {
        if (! $actor->can('manageParticipants', $file)) {
            throw new AuthorizationException;
        }

        $validated = Validator::make($data, [
            'participant_roles' => ['present', 'array'],
            'participant_roles.*' => ['string', Rule::in(RoleName::values(RoleName::staff()))],
            'participants_contribute' => ['boolean'],
        ])->validate();

        $file->participant_roles = SaveJobFile::participants($validated['participant_roles'], $file->responsible_role->value);
        $file->participants_contribute = (bool) ($validated['participants_contribute'] ?? false);
        $file->save();

        return $file;
    }
}
