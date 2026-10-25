<?php

namespace App\Actions\Fortify;

use App\Support\PasswordPolicy;

trait PasswordValidationRules
{
    /**
     * قواعد كلمة المرور الجديدة ببيانات صاحبها، فلا تحوي اسمه ولا بريده ولا جواله (PasswordPolicy).
     *
     * @return list<mixed>
     */
    protected function passwordRules(?string $name = null, ?string $email = null, ?string $phone = null): array
    {
        return PasswordPolicy::rules($name, $email, $phone);
    }
}
