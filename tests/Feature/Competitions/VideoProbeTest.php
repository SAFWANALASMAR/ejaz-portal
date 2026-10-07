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

function probeOutput(float $duration, array $packets, string $type = 'video'): string
{
    return (string) json_encode([
        'packets' => array_map(fn ($time) => ['pts_time' => (string) $time], $packets),
        'streams' => [['codec_type' => $type]],
        'format' => ['duration' => (string) $duration],
    ]);
}

it('takes the real duration from the last packet when the header claims less', function () {
    Process::fake(['*' => Process::result(probeOutput(30.0, [0, 1.5, 599.96]))]);

    expect(VideoProbe::seconds($this->file))->toBe(599.96);

    Process::assertRan(function (PendingProcess $process) {
        $command = (array) $process->command;

        return $command[0] === '/usr/bin/ffprobe'
            && in_array('-protocol_whitelist', $command, true)
            && end($command) === 'file:'.stream_get_meta_data($this->file)['uri'];
    });
});

it('takes the header duration when it is the larger one', function () {
    Process::fake(['*' => Process::result(probeOutput(42.5, [0, 10]))]);

    expect(VideoProbe::seconds($this->file))->toBe(42.5);
});

it('refuses a file without a video stream, unreadable, or with no duration', function (string $output, int $exitCode) {
    Process::fake(['*' => Process::result($output, '', $exitCode)]);

    expect(VideoProbe::seconds($this->file))->toBeNull();
})->with([
    'audio only' => [probeOutput(30, [], 'audio'), 0],
    'no streams' => ['{"packets":[],"streams":[],"format":{"duration":"30"}}', 0],
    'invalid data' => ['', 1],
    'zero duration' => [probeOutput(0, []), 0],
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
