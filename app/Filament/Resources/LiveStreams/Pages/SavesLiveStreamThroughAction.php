<?php

namespace App\Filament\Resources\LiveStreams\Pages;

use App\Actions\Livestream\SaveLiveStream;
use App\Filament\Concerns\SavesThroughAction;
use App\Models\LiveStream;
use App\Models\User;

/**
 * صفحتا إنشاء البث وتعديله تحفظان عبر SaveLiveStream (SavesThroughAction).
 */
trait SavesLiveStreamThroughAction
{
    use SavesThroughAction;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function saveLiveStream(LiveStream $stream, array $data): LiveStream
    {
        /** @var User $actor */
        $actor = auth()->user();

        return $this->saveThroughAction($stream, [], fn (): LiveStream => app(SaveLiveStream::class)->handle($actor, $stream, $data));
    }
}
