<?php

namespace App\Actions\Livestream;

use App\Enums\LiveStreamProvider;
use App\Models\LiveStream;
use App\Models\User;
use App\Rules\VideoUrl;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * حفظ بث: الصلاحية، ثم العنوان والموعدان (الانتهاء بعد البدء) والمزود ورابطه: يوتيوب برابط مقطع أو
 * بث صالح، وتيمز برابط انضمام من نطاق تيمز وحده. والمنقول إلى المكتبة لا يُعدَّل.
 */
class SaveLiveStream
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, LiveStream $stream, array $data): LiveStream
    {
        $allowed = $stream->exists ? $actor->can('update', $stream) : $actor->can('create', LiveStream::class);

        if (! $allowed) {
            throw new AuthorizationException;
        }

        if ($stream->archived_at !== null) {
            throw ValidationException::withMessages(['title' => __('livestream.validation.archived')]);
        }

        $validated = Validator::make($data, [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'provider' => ['required', Rule::enum(LiveStreamProvider::class)],
            'url' => ['required', 'string', 'max:500'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'is_published' => ['nullable', 'boolean'],
        ], attributes: __('livestream.attributes'))->validate();

        // من اللوحة يصل المزود تعداداً، ومن غيرها قيمته النصية
        $provider = $validated['provider'] instanceof LiveStreamProvider ? $validated['provider'] : LiveStreamProvider::from((string) $validated['provider']);
        $url = trim($validated['url']);

        if ($provider === LiveStreamProvider::Youtube && VideoUrl::youtubeId($url) === null) {
            throw ValidationException::withMessages(['url' => __('livestream.validation.youtube_url')]);
        }

        if ($provider === LiveStreamProvider::Teams && ! LiveStream::isTeamsUrl($url)) {
            throw ValidationException::withMessages(['url' => __('livestream.validation.teams_url')]);
        }

        $stream->fill([...$validated, 'url' => $url, 'is_published' => (bool) ($validated['is_published'] ?? false)]);
        $stream->save();

        return $stream;
    }
}
