<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication as BaseDisableTwoFactorAuthentication;

/**
 * لا يُوقف التحقق الثنائي لمن يشترطه دوره الإداري.
 */
class DisableTwoFactorAuthentication extends BaseDisableTwoFactorAuthentication
{
    /**
     * @param  mixed  $user
     */
    public function __invoke($user): void
    {
        abort_if($user instanceof User && $user->requiresTwoFactor(), 403);

        parent::__invoke($user);
    }
}
