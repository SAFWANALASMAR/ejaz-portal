<?php

namespace App\Listeners;

use App\Support\AccountSettings;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;

/**
 * رسالة التوثيق الآلية بعد التسجيل ما دام التوثيق مفعّلاً من اللوحة وحده (AccountSettings). تحل محل مستمع لارافيل
 * بالربط في AppServiceProvider، لأن لارافيل يسجّله لحدث التسجيل دائماً.
 */
class SendVerificationWhenRequired extends SendEmailVerificationNotification
{
    public function handle(Registered $event)
    {
        if (AccountSettings::verificationEnabled()) {
            parent::handle($event);
        }
    }
}
