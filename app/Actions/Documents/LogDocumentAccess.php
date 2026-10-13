<?php

namespace App\Actions\Documents;

use App\Enums\ActivityEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * تسجيل الاطلاع على معبأ أو عنصر أرشيف، وتنزيل ملفاته وطباعته (requirements/05: كل اطلاع وتنزيل
 * وتعديل يُسجَّل). والتعديل بسجل النموذج نفسه. ويُستدعى بعد فحص الصلاحية.
 */
class LogDocumentAccess
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function handle(User $actor, Model $subject, ActivityEvent $event, array $properties = []): void
    {
        activity('documents')->causedBy($actor)->performedOn($subject)
            ->event($event->value)
            ->withProperties($properties)
            ->log($event->value);
    }
}
