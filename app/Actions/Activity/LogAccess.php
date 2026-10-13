<?php

namespace App\Actions\Activity;

use App\Enums\ActivityEvent;
use App\Filament\Resources\Activities\ActivityResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * تسجيل الاطلاع على سجل وتنزيل ملفاته وطباعته باسم المنفّذ، في سجل وحدته: الجمعية العمومية (requirements/04)،
 * والنماذج والأرشيف (05)، وملفات الوظائف (11)، والسجلات (13). والإضافة والتعديل والحذف بسجل النموذج نفسه.
 * ويُستدعى بعد فحص الصلاحية.
 */
class LogAccess
{
    /**
     * @param  string  $log  اسم السجل (ActivityResource::LOGS)
     * @param  array<string, mixed>  $properties
     */
    public function handle(User $actor, Model $subject, string $log, ActivityEvent $event, array $properties = []): void
    {
        // سجل باسم خاطئ لا يظهر في تصفية سجل العمليات، فيضيع على المراجع
        if (! in_array($log, ActivityResource::LOGS, true)) {
            throw new InvalidArgumentException("Unknown activity log [{$log}].");
        }

        activity($log)->causedBy($actor)->performedOn($subject)
            ->event($event->value)
            ->withProperties($properties)
            ->log($event->value);
    }
}
