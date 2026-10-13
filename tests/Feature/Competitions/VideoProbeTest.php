<?php

use App\Support\VideoProbe;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    config(['competitions.video.ffprobe' => '/usr/bin/ffprobe']);
    $this->file = tmpfile();
    fwrite($this->file, 'x');
});

afterEach(fn () => fclose($this->file));

/**
 * ردّا الأداة بترتيبهما: الحاوية ومسار الفيديو (JSON صغير)، ثم توقيتات الحزم سطراً سطراً (CSV).
 */
function probeResults(float $duration, array $packets, string $type = 'video'): void
{
    Process::fake(['*' => Process::sequence([
        Process::result((string) json_encode(['streams' => [['codec_type' => $type]], 'format' => ['duration' => (string) $duration]])),
        Process::result(implode("\n", array_map(fn ($time) => (string) $time, $packets))."\n"),
    ])]);
}

it('takes the real duration from the last packet when the header claims less', function () {
    probeResults(30.0, [0, 1.5, 599.96, 599.9]);

    expect(VideoProbe::seconds($this->file))->toBe(599.96);

    Process::assertRan(function (PendingProcess $process) {
        $command = (array) $process->command;

        return $command[0] === '/usr/bin/ffprobe'
            && in_array('-protocol_whitelist', $command, true)
            && in_array('csv=p=0', $command, true)
            && end($command) === 'file:'.stream_get_meta_data($this->file)['uri'];
    });
});

it('takes the header duration when it is the larger one', function () {
    probeResults(42.5, [0, 10]);

    expect(VideoProbe::seconds($this->file))->toBe(42.5);
});

it('reads the packet times line by line, not as one decoded document', function () {
    // ساعة بستين إطاراً في الثانية: مئتا ألف حزمة وأكثر، فلا تُحوَّل إلى مصفوفات
    probeResults(30.0, range(0, 216000));

    expect(VideoProbe::seconds($this->file))->toBe(216000.0);
});

it('refuses a file without a video stream, unreadable, or with no duration', function (string $output, int $exitCode) {
    Process::fake(['*' => Process::sequence([Process::result($output, '', $exitCode), Process::result('')])]);

    expect(VideoProbe::seconds($this->file))->toBeNull();
})->with([
    'audio only' => [(string) json_encode(['streams' => [['codec_type' => 'audio']], 'format' => ['duration' => '30']]), 0],
    'no streams' => ['{"streams":[],"format":{"duration":"30"}}', 0],
    'invalid data' => ['', 1],
    'zero duration' => [(string) json_encode(['streams' => [['codec_type' => 'video']], 'format' => ['duration' => '0']]), 0],
    'not json' => ['garbage', 0],
]);

it('fails loudly when the configured tool is missing, so no video passes unchecked', function () {
    Process::fake(['*' => Process::result('', 'not found', 127)]);

    expect(fn () => VideoProbe::seconds($this->file))->toThrow(RuntimeException::class);
});

it('falls back to the MP4 header when no tool is configured', function () {
    config(['competitions.video.ffprobe' => '']);
    Process::fake();
    fwrite($this->file, 'not an mp4');

    expect(VideoProbe::seconds($this->file))->toBeNull();
    Process::assertNothingRan();
});
