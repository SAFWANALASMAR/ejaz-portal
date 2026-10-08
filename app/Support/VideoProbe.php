<?php

namespace App\Support;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * مدة المقطع الحقيقية بأداة ffprobe: يقرأ الحاوية وحزم مسار الفيديو (فك تغليف بلا فك ترميز)،
 * فالمدة أكبر قيمتين: المعلنة في الترويسة، وآخر توقيت حزمة فعلي، فلا تخدعه ترويسة تكتب مدة أقصر.
 * ولا يقبل إلا ملفاً فيه مسار فيديو، ويقرأ الملفات المحلية وحدها (protocol_whitelist).
 *
 * وبطلبين: الحاوية ومسار الفيديو بـ JSON صغير، ثم توقيتات الحزم سطراً سطراً بـ CSV تُقرأ بلا تحويلها
 * إلى مصفوفات، فمقطع ساعة بمئات آلاف الحزم لا يستهلك ذاكرة العامل.
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

        $path = 'file:'.(stream_get_meta_data($stream)['uri'] ?? '');

        $container = self::run($binary, ['-show_entries', 'format=duration:stream=codec_type', '-of', 'json', $path]);

        if (! $container->successful()) {
            return null;
        }

        $data = json_decode($container->output(), true);

        if (! is_array($data) || ($data['streams'][0]['codec_type'] ?? null) !== 'video') {
            return null;
        }

        $packets = self::run($binary, ['-show_entries', 'packet=pts_time', '-of', 'csv=p=0', $path]);

        if (! $packets->successful()) {
            return null;
        }

        // أكبر توقيت لا آخر سطر، لأن ترتيب الحزم قد لا يتبع التوقيت (إطارات B)
        $last = 0.0;
        $line = strtok($packets->output(), "\n");

        while ($line !== false) {
            $line = trim($line);

            if (is_numeric($line)) {
                $last = max($last, (float) $line);
            }

            $line = strtok("\n");
        }

        $seconds = max((float) ($data['format']['duration'] ?? 0), $last);

        return $seconds > 0 ? $seconds : null;
    }

    /**
     * @param  list<string>  $arguments
     */
    private static function run(string $binary, array $arguments): ProcessResult
    {
        $result = Process::timeout(300)->run([$binary, '-v', 'error', '-protocol_whitelist', 'file', '-select_streams', 'v:0', ...$arguments]);

        // 126 و127: الأداة غير موجودة أو لا تُشغَّل، وهذا خلل في البيئة لا في المقطع
        if (in_array($result->exitCode(), [126, 127], true)) {
            throw new RuntimeException('ffprobe is not available: '.$binary);
        }

        return $result;
    }
}
