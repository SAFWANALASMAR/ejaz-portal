<?php

use App\Jobs\ProcessSubmissionVideo;

/*
| طابور مقاطع المسابقات المستقل (docs/deploy/readiness.md §8): المهمة على طابورها، ولها في Horizon عمال
| بمهلتها في الإنتاج والتجريبية، فلا يقتلها حد العمال العام (60 ثانية) ولا تبقى التجريبية بلا عمال.
*/

it('queues the competition video job on its own queue', function () {
    expect((new ProcessSubmissionVideo(1))->queue)->toBe('videos');
});

it('gives the video queue workers with the job timeout in production and staging', function () {
    $job = new ProcessSubmissionVideo(1);
    $supervisor = config('horizon.defaults.supervisor-videos');

    expect($supervisor['queue'])->toBe(['videos'])
        ->and($supervisor['timeout'])->toBeGreaterThanOrEqual($job->timeout)
        ->and(config('horizon.defaults.supervisor-1.queue'))->not->toContain('videos');

    foreach (['production', 'staging'] as $environment) {
        expect(config('horizon.environments.'.$environment))->toHaveKeys(['supervisor-1', 'supervisor-videos']);
    }
});

it('documents a queue retry_after above the longest job timeout for production', function () {
    preg_match('/^REDIS_QUEUE_RETRY_AFTER=(\d+)$/m', (string) file_get_contents(base_path('.env.production.example')), $match);

    expect((int) ($match[1] ?? 0))->toBeGreaterThan((new ProcessSubmissionVideo(1))->timeout);
});
