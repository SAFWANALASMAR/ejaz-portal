<?php

namespace App\Actions\Fortify;

use App\Support\PasswordPolicy;

trait PasswordValidationRules
{
    /**
     * قواعد كلمة المرور الجديدة ببيانات صاحبها، فلا تحوي اسمه ولا اسم مستخدمه ولا بريده ولا جواله (PasswordPolicy).
     *
     * @return list<mixed>
     */
    protected function passwordRules(?string $name = null, ?string $email = null, ?string $phone = null, ?string $username = null): array
    {
        return PasswordPolicy::rules($name, $email, $phone, username: $username);
    }
}
