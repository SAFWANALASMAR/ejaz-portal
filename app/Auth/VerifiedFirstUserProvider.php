<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * البريد يتفرد على الموثق وحده (2026_10_26_090000_make_email_unique_only_when_verified)، فقد يحمله حساب غير موثق مع
 * صاحبه الموثق. ووسيط الاستعادة يبحث بالبريد (Password::sendResetLink والاستعادة بالرمز)، فيُقدَّم له الموثق، ولا
 * يصل الرابط غير الموثق أصلاً (User::sendPasswordResetNotification).
 */
class VerifiedFirstUserProvider extends EloquentUserProvider
{
    /**
     * @param  array<string, mixed>  $credentials
     */
    public function retrieveByCredentials(array $credentials): (Authenticatable&Model)|null
    {
        if (! array_key_exists('email', $credentials)) {
            return parent::retrieveByCredentials($credentials);
        }

        $query = $this->newModelQuery();

        foreach ($credentials as $key => $value) {
            if (! str_contains($key, 'password')) {
                $query->where($key, $value);
            }
        }

        /** @var (Authenticatable&Model)|null */
        return $query->orderByRaw('email_verified_at IS NULL')->orderBy('id')->first();
    }
}
