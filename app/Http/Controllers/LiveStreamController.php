<?php

namespace App\Http\Controllers;

use App\Models\LiveStream;
use Illuminate\Contracts\View\View;

/**
 * صفحة البث: البث الجاري، وإلا أقرب بث قادم منشور، وإلا 404؛ فتظهر الصفحة وتختفي بالموعد آلياً.
 * بلا تخزين مؤقت، فالحالة من الوقت واستعلامها واحد مفهرس.
 */
class LiveStreamController extends Controller
{
    public function __invoke(): View
    {
        $stream = LiveStream::query()->current()->first();

        abort_if($stream === null, 404);

        return view('live.show', [
            'stream' => $stream,
            'status' => $stream->status(),
            'labels' => [
                'days' => __('livestream.countdown.days'),
                'hours' => __('livestream.countdown.hours'),
                'minutes' => __('livestream.countdown.minutes'),
                'seconds' => __('livestream.countdown.seconds'),
            ],
        ]);
    }
}
