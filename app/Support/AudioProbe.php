<?php

namespace App\Support;

use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * مدة ملف الصوت في المكتبة بأداة ffprobe نفسها التي تفحص مقاطع المسابقات (COMPETITION_VIDEO_FFPROBE)، من مسار الصوت الأول، للملفات
 * المحلية وحدها (protocol_whitelist). فمدة برنامج الاستماع من الخادم لا من المتصفح. وبلا الأداة: null، فيُدخل المسؤول المدة.
 */
final class AudioProbe
{
    /**
     * @param  resource  $stream  ملف محلي مفتوح
     */
    public static function seconds($stream): ?float
    {
        $binary = (string) config('competitions.video.ffprobe');

        if ($binary === '') {
            return null;
        }

        $path = 'file:'.(stream_get_meta_data($stream)['uri'] ?? '');
        $result = Process::timeout(120)->run([$binary, '-v', 'error', '-protocol_whitelist', 'file', '-select_streams', 'a:0',
            '-show_entries', 'format=duration:stream=codec_type', '-of', 'json', $path]);

        // 126 و127: الأداة غير موجودة أو لا تُشغَّل، وهذا خلل في البيئة لا في الملف
        if (in_array($result->exitCode(), [126, 127], true)) {
            throw new RuntimeException('ffprobe is not available: '.$binary);
        }

        $data = $result->successful() ? json_decode($result->output(), true) : null;

        if (! is_array($data) || ($data['streams'][0]['codec_type'] ?? null) !== 'audio') {
            return null;
        }

        $seconds = (float) ($data['format']['duration'] ?? 0);

        return $seconds > 0 ? $seconds : null;
    }
}
