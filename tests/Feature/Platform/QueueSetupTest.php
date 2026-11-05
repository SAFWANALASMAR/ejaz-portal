<?php

use App\Jobs\GenerateCompetitionCertificate;
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

// إصدار الشهادات يضع مهمة لكل مشارك (17,500 في مسابقة الطلاب): في default كانت تؤخر رموز التحقق حتى تنتهي صلاحيتها
// (SendSms يتخطى رمزاً انتظر أكثر من عشر دقائق). فطابورها مستقل بعمال في كل بيئة (تدقيق 4 نوفمبر 2026)
it('queues the competition certificates on their own queue, away from the login codes in default', function () {
    $supervisor = config('horizon.defaults.supervisor-certificates');

    expect((new GenerateCompetitionCertificate(1))->queue)->toBe('certificates')
        ->and($supervisor['queue'])->toBe(['certificates'])
        ->and(config('horizon.defaults.supervisor-1.queue'))->not->toContain('certificates');

    foreach (['production', 'staging', 'local'] as $environment) {
        expect(config('horizon.environments.'.$environment))->toHaveKey('supervisor-certificates');
    }
});

it('documents a queue retry_after above the longest job timeout for production', function () {
    preg_match('/^REDIS_QUEUE_RETRY_AFTER=(\d+)$/m', (string) file_get_contents(base_path('.env.production.example')), $match);

    expect((int) ($match[1] ?? 0))->toBeGreaterThan((new ProcessSubmissionVideo(1))->timeout);
});
