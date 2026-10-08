<?php

namespace App\Actions\Assembly;

use App\Enums\ActivityEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * تسجيل تنزيل ملف من ملفات الجمعية العمومية (requirements/04: يُسجَّل التنزيل)، من الموقع أو اللوحة: من
 * نزّل، وأي ملف، ومتى. ويُستدعى بعد فحص الصلاحية.
 */
class LogFileDownload
{
    /**
     * @param  string  $file  أي ملف من السجل: file أو minutes أو attachment:<رقمه>
     */
    public function handle(User $actor, Model $subject, string $file): void
    {
        activity('assembly')->causedBy($actor)->performedOn($subject)
            ->event(ActivityEvent::FileDownloaded->value)
            ->withProperties(['file' => $file])
            ->log(ActivityEvent::FileDownloaded->value);
    }
}
