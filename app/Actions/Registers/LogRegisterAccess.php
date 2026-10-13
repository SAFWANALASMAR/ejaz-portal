<?php

namespace App\Actions\Registers;

use App\Enums\ActivityEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * تسجيل الاطلاع على مستند أو حساب منصة وتنزيل المرفقات (requirements/13: كل اطلاع وتنزيل وتعديل
 * يُسجَّل). والتعديل بسجل النموذج نفسه. ويُستدعى بعد فحص الصلاحية.
 */
class LogRegisterAccess
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function handle(User $actor, Model $subject, ActivityEvent $event, array $properties = []): void
    {
        activity('registers')->causedBy($actor)->performedOn($subject)
            ->event($event->value)
            ->withProperties($properties)
            ->log($event->value);
    }
}
