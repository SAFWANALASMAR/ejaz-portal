<?php

namespace App\Actions\Services;

use App\Models\Opening;
use App\Models\User;
use App\Support\FormFieldRules;
use Illuminate\Auth\Access\AuthorizationException;
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
        $opening->form_fields = FormFieldRules::clean($opening->form_fields ?? []);

        $opening->save();

        return $opening;
    }
}
