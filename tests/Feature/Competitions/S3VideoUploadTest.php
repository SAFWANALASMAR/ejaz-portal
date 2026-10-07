<?php

use App\Contracts\VideoUploads;
use App\Enums\VideoStatus;
use App\Models\Competition;
use App\Models\SubmissionVideo;
use App\Models\User;
use App\Services\VideoUploads\S3VideoUploads;
use Aws\CommandInterface;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Utils;

/**
 * تخزين S3 وهمي في الذاكرة: يكفي لعمليات الرفع المجزأ التي يستدعيها S3VideoUploads.
 */
final class FakeS3
{
    /** @var array<string, string> */
    public array $objects = [];

    /** @var array<string, array{key: string, parts: array<int, string>}> */
    public array $uploads = [];

    /** @var list<string> */
    public array $calls = [];

    private int $next = 0;

    public function client(): S3Client
    {
        return new S3Client([
            'region' => 'me-central-1',
            'version' => 'latest',
            'credentials' => ['key' => 'test', 'secret' => 'test'],
            'endpoint' => 'https://storage.example.sa',
            'use_path_style_endpoint' => true,
            'handler' => fn (CommandInterface $command) => $this->handle($command),
        ]);
    }

    /** المتصفح يرفع الجزء مباشرة إلى التخزين */
    public function uploadPart(string $uploadId, int $number, string $body): void
    {
        $this->uploads[$uploadId]['parts'][$number] = $body;
    }

    private function handle(CommandInterface $command)
    {
        $this->calls[] = $command->getName();
        $missing = fn () => Create::rejectionFor(new S3Exception('NoSuchUpload', $command, ['code' => 'NoSuchUpload']));

        switch ($command->getName()) {
            case 'CreateMultipartUpload':
                $id = 'upload-'.$this->next++;
                $this->uploads[$id] = ['key' => $command['Key'], 'parts' => []];

                return Create::promiseFor(new Result(['UploadId' => $id]));
            case 'ListParts':
                if (! isset($this->uploads[$command['UploadId']])) {
                    return $missing();
                }

                $parts = [];
                foreach ($this->uploads[$command['UploadId']]['parts'] as $number => $body) {
                    $parts[] = ['PartNumber' => $number, 'Size' => strlen($body), 'ETag' => '"'.md5($body).'"'];
                }

                return Create::promiseFor(new Result(['Parts' => $parts, 'IsTruncated' => false]));
            case 'CompleteMultipartUpload':
                $upload = $this->uploads[$command['UploadId']];
                $this->objects[$upload['key']] = implode('', array_map(fn (array $part) => $upload['parts'][$part['PartNumber']], $command['MultipartUpload']['Parts']));
                unset($this->uploads[$command['UploadId']]);

                return Create::promiseFor(new Result([]));
            case 'AbortMultipartUpload':
                if (! isset($this->uploads[$command['UploadId']])) {
                    return $missing();
                }
                unset($this->uploads[$command['UploadId']]);

                return Create::promiseFor(new Result([]));
            case 'GetObject':
                return Create::promiseFor(new Result(['Body' => Utils::streamFor($this->objects[$command['Key']])]));
            case 'CopyObject':
                [, $source] = explode('/', $command['CopySource'], 2);
                $this->objects[$command['Key']] = $this->objects[rawurldecode($source)];

                return Create::promiseFor(new Result([]));
            case 'DeleteObject':
                unset($this->objects[$command['Key']]);

                return Create::promiseFor(new Result([]));
            case 'ListMultipartUploads':
                $uploads = [];
                foreach ($this->uploads as $id => $upload) {
                    $uploads[] = ['Key' => $upload['key'], 'UploadId' => $id];
                }

                return Create::promiseFor(new Result(['Uploads' => $uploads, 'IsTruncated' => false]));
        }

        throw new LogicException('Unexpected S3 command '.$command->getName());
    }
}

beforeEach(function () {
    $this->s3 = new FakeS3;
    app()->instance(VideoUploads::class, new S3VideoUploads($this->s3->client(), 'ejaz-private', 'private'));

    $this->competition = Competition::factory()->withQuestions()->create(['slug' => 'demo']);
    $this->video = $this->competition->questions[2];
    $this->applicant = User::factory()->create(['phone' => '+966500000001', 'phone_verified_at' => now()]);
    $this->actingAs($this->applicant)->get('/competitions/demo/entry');
    $this->contents = mp4Contents(seconds: 30, padding: 5 * 1024 * 1024 + 500);
});

function startS3Upload(string $contents): array
{
    return test()->postJson('/competitions/demo/entry/videos/'.test()->video->id, ['size' => strlen($contents), 'type' => 'video/mp4', 'duration' => 30])->assertOk()->json();
}

it('uploads straight to the storage with signed part links, then checks and keeps the video', function () {
    $start = startS3Upload($this->contents);
    $video = SubmissionVideo::sole();

    expect($video->upload_id)->toBe('upload-0')
        ->and($start['urls'])->toHaveCount(2)
        ->and($start['urls'][0])->toStartWith('https://storage.example.sa/ejaz-private/private/competition-uploads/'.$video->uuid.'.mp4?')
        ->toContain('partNumber=1', 'uploadId=upload-0', 'X-Amz-Signature=', 'X-Amz-Expires=7200')
        ->and($start['urls'][1])->toContain('partNumber=2');

    $this->s3->uploadPart('upload-0', 1, substr($this->contents, 0, 5 * 1024 * 1024));

    // بعد انقطاع: رابط الجزء الناقص وحده، والإنهاء يُرفض قبل اكتماله
    expect(array_keys($this->getJson('/competition-videos/'.$video->uuid)->json('urls')))->toBe([1]);
    $this->postJson('/competition-videos/'.$video->uuid.'/complete')->assertUnprocessable();

    $this->s3->uploadPart('upload-0', 2, substr($this->contents, 5 * 1024 * 1024));
    $this->postJson('/competition-videos/'.$video->uuid.'/complete')->assertStatus(202);

    $video->refresh();
    expect($video->status)->toBe(VideoStatus::Ready)
        ->and($video->duration_seconds)->toBe(30)
        ->and($video->path)->toStartWith('competition-videos/')
        ->and($this->s3->objects)->toBe(['private/'.$video->path => $this->contents])
        ->and($this->s3->uploads)->toBe([]);
});

it('counts a part of the wrong size as missing, so it is uploaded again', function () {
    startS3Upload($this->contents);
    $video = SubmissionVideo::sole();

    $this->s3->uploadPart('upload-0', 1, str_repeat('x', 100));
    $this->s3->uploadPart('upload-0', 2, substr($this->contents, 5 * 1024 * 1024));

    expect(app(VideoUploads::class)->missing($video))->toBe([0]);
    $this->postJson('/competition-videos/'.$video->uuid.'/complete')->assertUnprocessable();
});

it('refuses chunks sent to the application when uploads go straight to the storage', function () {
    startS3Upload($this->contents);
    $video = SubmissionVideo::sole();

    $this->call('PUT', URL::temporarySignedRoute('competitions.videos.chunk', now()->addHour(), ['video' => $video->uuid, 'index' => 0]), [], [], [], ['CONTENT_LENGTH' => '10'], str_repeat('x', 10))
        ->assertNotFound();
});

it('leaves out of the video any part beyond its count', function () {
    startS3Upload($this->contents);
    $video = SubmissionVideo::sole();
    $this->s3->uploadPart('upload-0', 1, substr($this->contents, 0, 5 * 1024 * 1024));
    $this->s3->uploadPart('upload-0', 2, substr($this->contents, 5 * 1024 * 1024));
    // جزء زائد بعد الفحص لا يدخل في المقطع
    $this->s3->uploadPart('upload-0', 3, 'extra');

    $this->postJson('/competition-videos/'.$video->uuid.'/complete')->assertStatus(202);

    expect($video->fresh()->status)->toBe(VideoStatus::Ready)
        ->and(array_values($this->s3->objects))->toBe([$this->contents]);
});

it('aborts the previous upload when the applicant starts again', function () {
    startS3Upload($this->contents);
    startS3Upload($this->contents);

    expect(array_keys($this->s3->uploads))->toBe(['upload-1'])
        ->and(SubmissionVideo::sole()->upload_id)->toBe('upload-1');
});

it('aborts abandoned and orphan uploads on pruning', function () {
    startS3Upload($this->contents);
    $this->s3->uploads['stray'] = ['key' => 'private/competition-uploads/stray.mp4', 'parts' => []];

    $this->artisan('ejaz:prune-video-uploads')->assertSuccessful();
    expect(array_keys($this->s3->uploads))->toBe(['upload-0']);

    $this->travel(2)->days();
    $this->artisan('ejaz:prune-video-uploads')->assertSuccessful();

    expect($this->s3->uploads)->toBe([])
        ->and(SubmissionVideo::sole()->rejection_reason)->toBe('abandoned');
});

it('needs an s3 private disk for direct uploads', function () {
    app()->forgetInstance(VideoUploads::class);
    config(['competitions.video.upload' => 's3', 'filesystems.private_disk' => 'local']);

    expect(fn () => app(VideoUploads::class))->toThrow(InvalidArgumentException::class);

    config(['filesystems.private_disk' => 's3', 'filesystems.disks.s3.bucket' => 'b', 'filesystems.disks.s3.region' => 'me-central-1']);
    expect(app(VideoUploads::class))->toBeInstanceOf(S3VideoUploads::class);
});
