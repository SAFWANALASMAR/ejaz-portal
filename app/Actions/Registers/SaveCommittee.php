<?php

namespace App\Actions\Registers;

use App\Models\Committee;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * حفظ لجنة باسمها كما تُدخله الجمعية (السكرتير — CommitteePolicy). ويُسجَّل الحفظ.
 */
class SaveCommittee
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, Committee $committee, array $data): Committee
    {
        if (! ($committee->exists ? $actor->can('update', $committee) : $actor->can('create', Committee::class))) {
            throw new AuthorizationException;
        }

        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:200', Rule::unique('committees', 'name')->ignore($committee->id)],
            'description' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        $committee->fill(['name' => $validated['name'], 'description' => $validated['description'] ?? null])->save();

        return $committee;
    }
}
