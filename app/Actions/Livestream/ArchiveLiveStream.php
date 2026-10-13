<?php

namespace App\Actions\Livestream;

use App\Actions\Library\SaveLibraryItem;
use App\Enums\ActivityEvent;
use App\Enums\LibrarySection;
use App\Enums\LiveStreamProvider;
use App\Enums\LiveStreamStatus;
use App\Models\LibraryItem;
use App\Models\LiveStream;
use App\Models\User;
use App\Rules\VideoUrl;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * نقل تسجيل بث منتهٍ إلى المكتبة المرئية: عنصر جديد غير منشور (يراجعه المحرر ثم ينشره) برابط التسجيل —
 * رابط بث يوتيوب نفسه، أو رابط يُدخله المحرر (ولتيمز لا بد منه). يُنفَّذ مرة واحدة، ويُربط العنصر بالبث
 * فيختفي البث من صفحته. يتطلب صلاحية تعديل البث وإنشاء عنصر مكتبة (يفحصها SaveLibraryItem بنفسه).
 */
class ArchiveLiveStream
{
    public function __construct(private readonly SaveLibraryItem $saveLibraryItem) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, LiveStream $stream, ?string $recordingUrl = null): LibraryItem
    {
        if (! $actor->can('update', $stream) || ! $actor->can('create', LibraryItem::class)) {
            throw new AuthorizationException;
        }

        if ($stream->archived_at !== null) {
            throw ValidationException::withMessages(['recording_url' => __('livestream.validation.already_archived')]);
        }

        if ($stream->status() !== LiveStreamStatus::Ended) {
            throw ValidationException::withMessages(['recording_url' => __('livestream.validation.not_ended')]);
        }

        $url = filled($recordingUrl) ? trim((string) $recordingUrl) : ($stream->provider === LiveStreamProvider::Youtube ? $stream->url : null);

        if ($url === null) {
            throw ValidationException::withMessages(['recording_url' => __('livestream.validation.recording_required')]);
        }

        if (! VideoUrl::isValid($url)) {
            throw ValidationException::withMessages(['recording_url' => __('validation.video_url')]);
        }

        return DB::transaction(function () use ($actor, $stream, $url): LibraryItem {
            $item = $this->saveLibraryItem->handle($actor, new LibraryItem, [
                'slug' => 'live-'.$stream->id.'-'.$stream->starts_at->format('Ymd'),
                'title' => $stream->title,
                'section' => LibrarySection::Videos,
                'description' => $stream->description,
                'published_on' => $stream->starts_at->toDateString(),
                'tags' => [],
                'video_url' => $url,
                'is_published' => false,
            ]);

            $stream->library_item_id = $item->id;
            $stream->archived_at = now();
            $stream->save();

            activity('content')->performedOn($stream)->causedBy($actor)
                ->event(ActivityEvent::LiveStreamArchived->value)
                ->withProperties(['library_item_id' => $item->id])
                ->log(ActivityEvent::LiveStreamArchived->value);

            return $item;
        });
    }
}
