<?php

namespace App\Actions\Assembly;

use App\Enums\ActivityEvent;
use App\Models\Meeting;
use App\Models\User;
use App\Support\PrivatePdf;
use App\Support\StoragePath;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * رفع محضر اجتماع منعقد مسودةً (السكرتير)، تستبدل مسودة سابقة ويُحذف ملفها. ولا يُرفع بعد الاعتماد
 * (MeetingPolicy::uploadMinutes)، والمسودة لا تظهر للأعضاء حتى يعتمدها المجلس.
 */
class UploadMeetingMinutes
{
    /**
     * @param  string  $path  الملف المرفوع في مجلد المحاضر بالقرص الخاص
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, Meeting $meeting, string $path): Meeting
    {
        if (! $actor->can('uploadMinutes', $meeting)) {
            throw new AuthorizationException;
        }

        // ملف محضر آخر لا يُعاد استعماله، فلا يحذفه استبدالُه هنا
        if (Meeting::withTrashed()->where('minutes_file', $path)->whereKeyNot($meeting->id)->exists()) {
            throw ValidationException::withMessages(['minutes_file' => __('validation.file_in_use')]);
        }

        $size = PrivatePdf::check(Meeting::disk(), Meeting::MINUTES_DIRECTORY, $path, 'minutes_file');

        $previous = DB::transaction(function () use ($actor, $meeting, $path, $size): ?string {
            /** @var Meeting $locked */
            $locked = Meeting::query()->whereKey($meeting->id)->lockForUpdate()->firstOrFail();

            // اعتُمد بين فتح الصفحة والطلب
            if (! $actor->can('uploadMinutes', $locked)) {
                throw new AuthorizationException;
            }

            $previous = $locked->minutes_file;

            $locked->forceFill([
                'minutes_file' => $path,
                'minutes_size' => $size,
                'minutes_uploaded_by' => $actor->id,
                'minutes_approved_by' => null,
                'minutes_approved_at' => null,
            ])->save();

            activity('assembly')->causedBy($actor)->performedOn($locked)
                ->event(ActivityEvent::MinutesUploaded->value)
                ->log(ActivityEvent::MinutesUploaded->value);

            $meeting->setRawAttributes($locked->getAttributes(), true);

            return $previous;
        });

        if ($previous !== null && $previous !== $path && StoragePath::inDirectory($previous, Meeting::MINUTES_DIRECTORY)) {
            Storage::disk(Meeting::disk())->delete($previous);
        }

        return $meeting;
    }
}
