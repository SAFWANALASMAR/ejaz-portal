<?php

use App\Contracts\VideoUploads;
use App\Models\Document;
use App\Models\SubmissionVideo;
use App\Services\VideoUploads\S3VideoUploads;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Storage;

/*
| التخزين بواجهة S3 القياسية لدى أي مزود داخل المملكة (decisions.md، 11 أكتوبر 2026): المضيف والمنطقة
| ونمط المسار والحاوية من البيئة وحدها، فيعمل القرص الخاص والرفع المباشر لمقاطع المسابقات بلا تغيير في الشيفرة.
| ولا طلب شبكة هنا: الروابط الموقّعة تُحسب محلياً.
*/

beforeEach(function () {
    config([
        'filesystems.private_disk' => 's3',
        'filesystems.disks.s3.key' => 'test-key',
        'filesystems.disks.s3.secret' => 'test-secret',
        'filesystems.disks.s3.region' => 'me-jeddah-1',
        'filesystems.disks.s3.bucket' => 'ejaz-private',
        'filesystems.disks.s3.endpoint' => 'https://objectstorage.example.sa',
        'filesystems.disks.s3.use_path_style_endpoint' => true,
    ]);
    Storage::forgetDisk('s3');
});

it('serves the private disk from the configured S3-compatible endpoint', function () {
    $disk = Storage::disk(Document::disk());

    expect($disk)->toBeInstanceOf(AwsS3V3Adapter::class);

    $url = $disk->temporaryUrl('governance/report.pdf', now()->addMinutes(5));

    expect($url)->toStartWith('https://objectstorage.example.sa/ejaz-private/governance/report.pdf?')
        ->toContain('X-Amz-Signature=')
        ->toContain('me-jeddah-1');
});

it('signs direct competition upload parts against the configured endpoint', function () {
    config(['competitions.video.upload' => 's3']);
    app()->forgetInstance(VideoUploads::class);

    $uploads = app(VideoUploads::class);
    expect($uploads)->toBeInstanceOf(S3VideoUploads::class);

    $video = new SubmissionVideo;
    $video->uuid = '7f0c2d1e-0000-4000-8000-000000000001';
    $video->upload_id = 'upload-1';

    $urls = $uploads->urls($video, [0, 1]);

    expect($urls[0])->toStartWith('https://objectstorage.example.sa/ejaz-private/competition-uploads/7f0c2d1e-0000-4000-8000-000000000001.mp4?')
        ->toContain('partNumber=1')
        ->toContain('uploadId=upload-1')
        ->and($urls[1])->toContain('partNumber=2');
});

it('passes the checksum settings for providers that reject the newer AWS checksums', function () {
    config([
        'filesystems.disks.s3.request_checksum_calculation' => 'when_required',
        'filesystems.disks.s3.response_checksum_validation' => 'when_required',
    ]);

    $client = Storage::disk('s3')->getClient();

    expect($client->getConfig('request_checksum_calculation'))->toBe('when_required')
        ->and($client->getConfig('response_checksum_validation'))->toBe('when_required');
});
