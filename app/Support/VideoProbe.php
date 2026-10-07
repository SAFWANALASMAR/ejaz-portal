<?php

namespace App\Support;

use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * مدة المقطع الحقيقية بأداة ffprobe: يقرأ الحاوية وحزم مسار الفيديو (فك تغليف بلا فك ترميز)،
 * فالمدة أكبر قيمتين: المعلنة في الترويسة، وآخر توقيت حزمة فعلي، فلا تخدعه ترويسة تكتب مدة أقصر.
 * ولا يقبل إلا ملفاً فيه مسار فيديو، ويقرأ الملفات المحلية وحدها (protocol_whitelist).
 *
 * وإن لم يُضبط مسار الأداة (COMPETITION_VIDEO_FFPROBE فارغاً) فالمدة من ترويسة MP4 (Mp4Duration).
 * وإن ضُبط وتعذر تشغيلها فاستثناء، فتعاد المهمة ولا يُقبل مقطع بلا فحص.
 */
final class VideoProbe
{
    /**
     * @param  resource  $stream  ملف محلي مفتوح
     */
    public static function seconds($stream): ?float
    {
        $binary = (string) config('competitions.video.ffprobe');

        if ($binary === '') {
            return Mp4Duration::seconds($stream);
        }

        $path = stream_get_meta_data($stream)['uri'] ?? '';

        $result = Process::timeout(300)->run([
            $binary, '-v', 'error', '-protocol_whitelist', 'file',
            '-select_streams', 'v:0',
            '-show_entries', 'format=duration:stream=codec_type:packet=pts_time',
            '-of', 'json', 'file:'.$path,
        ]);

        // 126 و127: الأداة غير موجودة أو لا تُشغَّل، وهذا خلل في البيئة لا في المقطع
        if (in_array($result->exitCode(), [126, 127], true)) {
            throw new RuntimeException('ffprobe is not available: '.$binary);
        }

        if (! $result->successful()) {
            return null;
        }

        $data = json_decode($result->output(), true);

        if (! is_array($data) || ($data['streams'][0]['codec_type'] ?? null) !== 'video') {
            return null;
        }

        $times = array_filter(array_column((array) ($data['packets'] ?? []), 'pts_time'), is_numeric(...));
        $seconds = max((float) ($data['format']['duration'] ?? 0), $times === [] ? 0.0 : (float) max($times));

        return $seconds > 0 ? $seconds : null;
    }
}
