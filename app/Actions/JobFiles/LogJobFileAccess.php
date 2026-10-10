<?php

namespace App\Actions\JobFiles;

use App\Enums\ActivityEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * تسجيل الاطلاع على ملف وظيفة وتنزيل مستنداته باسم المنفّذ (requirements/11). والإضافة والتعديل والحذف
 * بسجل النموذج نفسه. ويُستدعى بعد فحص الصلاحية.
 */
class LogJobFileAccess
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function handle(User $actor, Model $subject, ActivityEvent $event, array $properties = []): void
    {
        activity('job_files')->causedBy($actor)->performedOn($subject)
            ->event($event->value)
            ->withProperties($properties)
            ->log($event->value);
    }
}
