<?php

namespace App\Actions\Registers;

use App\Models\PlatformAccount;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * حفظ حساب منصة (المدير التنفيذي والسكرتير — PlatformAccountPolicy). الحقول المعرّفة وحدها تُحفظ، فلا
 * تُخزَّن كلمة مرور ولو أُرسلت (قرار مسجّل). ورابط الدخول https وحده. ويُسجَّل الحفظ.
 */
class SavePlatformAccount
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, PlatformAccount $account, array $data): PlatformAccount
    {
        if (! ($account->exists ? $actor->can('update', $account) : $actor->can('create', PlatformAccount::class))) {
            throw new AuthorizationException;
        }

        $validated = Validator::make($data, [
            'platform' => ['required', 'string', 'max:200'],
            'responsible_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'username' => ['nullable', 'string', 'max:200'],
            'login_url' => ['nullable', 'string', 'max:500', 'url:https'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        if (isset($validated['responsible_id'])) {
            $responsible = User::query()->find($validated['responsible_id']);

            if ($responsible === null || ! $responsible->is_active || $responsible->anonymized_at !== null || ! $responsible->can('platform_accounts.view')) {
                throw ValidationException::withMessages(['responsible_id' => __('registers.validation.platform_responsible')]);
            }
        }

        $account->fill([
            'platform' => $validated['platform'],
            'responsible_id' => $validated['responsible_id'] ?? null,
            'username' => $validated['username'] ?? null,
            'login_url' => $validated['login_url'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ])->save();

        return $account;
    }
}
