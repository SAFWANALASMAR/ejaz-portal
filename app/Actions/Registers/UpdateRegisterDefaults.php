<?php

namespace App\Actions\Registers;

use App\Models\RegisterEntry;
use App\Models\Setting;
use App\Models\User;
use App\Support\Registers;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * ضبط مدة التنبيه الافتراضية للمستندات الجديدة (من يدير السجلات). تُسجَّل بسجل نموذج Setting.
 */
class UpdateRegisterDefaults
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, array $data): void
    {
        if (! $actor->can('create', RegisterEntry::class)) {
            throw new AuthorizationException;
        }

        $validated = Validator::make($data, [
            'default_alert_days' => ['required', 'integer', 'between:1,730'],
        ], attributes: ['default_alert_days' => __('registers.fields.default_alert_days')])->validate();

        Setting::put(Registers::SETTINGS_KEY, ['default_alert_days' => (int) $validated['default_alert_days']]);
    }
}
