<?php

namespace App\Services\VideoUploads;

use App\Contracts\VideoUploads;
use App\Enums\VideoStatus;
use App\Exceptions\VideoRejected;
use App\Models\SubmissionVideo;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use DateTimeInterface;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * الرفع المباشر من المتصفح إلى تخزين متوافق مع S3 (القرص الخاص نفسه)، برفع مجزأ (multipart):
 * لكل جزء رابط PUT موقّع مؤقت، فلا يمر المقطع بخادم التطبيق.
 *
 * والحجم لا يدخل في توقيع الرابط (S3 لا يوقّع Content-Length)، فيُفحص من قائمة الأجزاء: الجزء
 * المخالف لحجمه يُعدّ ناقصاً فيعيد المتصفح رفعه، والمقطع كاملاً يُفحص حجمه في الطابور. وإكمال
 * الرفع يتم من الخادم في الطابور بأجزائه المسجلة، لا بقائمة يرسلها المتصفح.
 *
 * والرفع غير المكتمل يُلغى بإلغاء المقطع أو ejaz:prune-video-uploads، ويُضبط في الحاوية مع ذلك
 * قاعدة AbortIncompleteMultipartUpload بعد يوم (docs/decisions.md).
 */
final class S3VideoUploads implements VideoUploads
{
    public const UPLOAD_DIRECTORY = 'competition-uploads';

    public function __construct(private S3Client $client, private string $bucket, private string $root = '') {}

    public function begin(SubmissionVideo $video): void
    {
        $result = $this->client->createMultipartUpload([
            'Bucket' => $this->bucket,
            'Key' => $this->key($this->uploadPath($video)),
            'ContentType' => 'video/mp4',
        ]);

        $video->upload_id = (string) $result['UploadId'];
        $video->save();
    }

    public function urls(SubmissionVideo $video, array $indexes): array
    {
        // مدة نسبية تحسبها المكتبة من لحظة التوقيع نفسها، فتبقى المدة في الرابط (X-Amz-Expires) ثابتة. وتاريخ
        // من now() كان يُطرح منه time() بعده، فتنقص ثانية إن مرّت بينهما، وتتبع ساعة الاختبار لا ساعة الخادم
        $expires = '+'.(int) config('competitions.video.url_minutes').' minutes';
        $urls = [];

        foreach ($indexes as $index) {
            $command = $this->client->getCommand('UploadPart', [
                'Bucket' => $this->bucket,
                'Key' => $this->key($this->uploadPath($video)),
                'UploadId' => $video->upload_id,
                'PartNumber' => $index + 1,
            ]);

            $urls[$index] = (string) $this->client->createPresignedRequest($command, $expires)->getUri();
        }

        return $urls;
    }

    public function missing(SubmissionVideo $video): array
    {
        $sizes = [];

        foreach ($this->parts($video) as $part) {
            $sizes[(int) $part['PartNumber'] - 1] = (int) $part['Size'];
        }

        return array_values(array_filter(
            range(0, $video->chunk_count - 1),
            fn (int $index): bool => ($sizes[$index] ?? null) !== $video->expectedChunkSize($index),
        ));
    }

    public function open(SubmissionVideo $video)
    {
        $this->complete($video);

        // الحجم قبل التنزيل: كائن أكبر من المعلن لا يُكتب على قرص العامل
        $stored = (int) $this->client->headObject(['Bucket' => $this->bucket, 'Key' => $this->key($this->uploadPath($video))])['ContentLength'];

        if ($stored !== $video->size) {
            throw new VideoRejected('size_mismatch');
        }

        $target = tmpfile();

        if ($target === false) {
            throw new RuntimeException('Cannot create a temporary file for the video.');
        }

        $body = $this->client->getObject([
            'Bucket' => $this->bucket,
            'Key' => $this->key($this->uploadPath($video)),
            '@http' => ['stream' => true],
        ])['Body'];

        $written = 0;

        while (! $body->eof()) {
            $written += (int) fwrite($target, $body->read(1024 * 1024));

            // ولا يتجاوز النسخ الحجم المعلن ولو تغيّر الكائن بين الفحص والتنزيل
            if ($written > $video->size) {
                fclose($target);

                throw new VideoRejected('size_mismatch');
            }
        }

        rewind($target);

        return $target;
    }

    public function keep(SubmissionVideo $video, $file): string
    {
        $path = SubmissionVideo::DIRECTORY.'/'.Str::random(40).'.mp4';

        // نسخ داخل التخزين بلا رفع جديد، ثم تحذف discard() نسخة الرفع
        $this->client->copyObject([
            'Bucket' => $this->bucket,
            'Key' => $this->key($path),
            'CopySource' => $this->bucket.'/'.implode('/', array_map(rawurlencode(...), explode('/', $this->key($this->uploadPath($video))))),
        ]);

        return $path;
    }

    public function discard(SubmissionVideo $video): void
    {
        if (filled($video->upload_id)) {
            try {
                $this->client->abortMultipartUpload(['Bucket' => $this->bucket, 'Key' => $this->key($this->uploadPath($video)), 'UploadId' => $video->upload_id]);
            } catch (S3Exception $exception) {
                // اكتمل الرفع أو أُلغي من قبل
                if ($exception->getAwsErrorCode() !== 'NoSuchUpload') {
                    throw $exception;
                }
            }
        }

        $this->client->deleteObject(['Bucket' => $this->bucket, 'Key' => $this->key($this->uploadPath($video))]);
    }

    /**
     * الرفوع المجزأة التي لا مقطع يُرفع أو يُعالج بمعرّفها. وما بدأ في الساعة الأخيرة يُترك، فقد يكون
     * بين بدئه في التخزين وحفظ معرّفه في المقطع (begin).
     */
    public function pruneOrphans(): int
    {
        $orphans = 0;
        $recent = now()->subHour();

        foreach ($this->client->getPaginator('ListMultipartUploads', ['Bucket' => $this->bucket, 'Prefix' => $this->key(self::UPLOAD_DIRECTORY.'/')]) as $page) {
            foreach ((array) ($page['Uploads'] ?? []) as $upload) {
                if (($upload['Initiated'] ?? null) instanceof DateTimeInterface && $upload['Initiated'] > $recent) {
                    continue;
                }

                $active = SubmissionVideo::query()->where('upload_id', $upload['UploadId'])
                    ->whereIn('status', [VideoStatus::Uploading, VideoStatus::Processing])->exists();

                if (! $active) {
                    $this->client->abortMultipartUpload(['Bucket' => $this->bucket, 'Key' => $upload['Key'], 'UploadId' => $upload['UploadId']]);
                    $orphans++;
                }
            }
        }

        return $orphans;
    }

    /**
     * يكمل الرفع بأجزائه المسجلة في التخزين. وإعادة المهمة بعد إكماله تجد الرفع منتهياً فتمضي.
     *
     * والأجزاء تُفحص من جديد هنا: الرابط الموقّع يبقى صالحاً بعد طلب الإكمال، والحجم لا يدخل في توقيعه،
     * فقد يُستبدل جزء بأكبر منه. فكل جزء بحجمه المتوقع تماماً، وإلا أُلغي الرفع ورُفض المقطع.
     *
     * @throws VideoRejected
     */
    private function complete(SubmissionVideo $video): void
    {
        try {
            $parts = $this->parts($video);
        } catch (S3Exception $exception) {
            if ($exception->getAwsErrorCode() === 'NoSuchUpload') {
                return;
            }

            throw $exception;
        }

        // أجزاء المقطع وحدها بترتيبها، وما زاد عليها لا يدخل فيه
        $parts = array_values(array_filter(
            $parts,
            fn (array $part): bool => (int) $part['PartNumber'] >= 1 && (int) $part['PartNumber'] <= $video->chunk_count,
        ));
        usort($parts, fn (array $a, array $b): int => (int) $a['PartNumber'] <=> (int) $b['PartNumber']);

        $sized = count($parts) === $video->chunk_count && collect($parts)->every(
            fn (array $part): bool => (int) $part['Size'] === $video->expectedChunkSize((int) $part['PartNumber'] - 1),
        );

        if (! $sized) {
            $this->discard($video);

            throw new VideoRejected('size_mismatch');
        }

        $parts = array_map(fn (array $part): array => ['PartNumber' => (int) $part['PartNumber'], 'ETag' => $part['ETag']], $parts);

        $this->client->completeMultipartUpload([
            'Bucket' => $this->bucket,
            'Key' => $this->key($this->uploadPath($video)),
            'UploadId' => $video->upload_id,
            'MultipartUpload' => ['Parts' => $parts],
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parts(SubmissionVideo $video): array
    {
        $parts = [];

        foreach ($this->client->getPaginator('ListParts', ['Bucket' => $this->bucket, 'Key' => $this->key($this->uploadPath($video)), 'UploadId' => $video->upload_id]) as $page) {
            foreach ((array) ($page['Parts'] ?? []) as $part) {
                $parts[] = $part;
            }
        }

        return $parts;
    }

    private function uploadPath(SubmissionVideo $video): string
    {
        return self::UPLOAD_DIRECTORY.'/'.$video->uuid.'.mp4';
    }

    /**
     * المسار داخل الحاوية بجذر القرص (root) إن ضُبط، كما يكتبه Storage.
     */
    private function key(string $path): string
    {
        return ltrim(trim($this->root, '/').'/'.$path, '/');
    }
}
