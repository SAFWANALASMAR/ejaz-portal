<?php

namespace App\Models;

use App\Enums\VideoStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * مقطع فيديو لسؤال في مشاركة: يُرفع أجزاءً إلى القرص الخاص، ثم يُجمع ويُفحص في الخلفية
 * (ProcessSubmissionVideo). والمسار عشوائي، ولا يُقدَّم إلا بعد فحص الصلاحية.
 *
 * @property VideoStatus $status
 */
class SubmissionVideo extends Model
{
    use HasUuids;

    /**
     * لا تعبئة جماعية: كل الحقول يضبطها الإجراء نفسه.
     *
     * @var list<string>
     */
    protected $fillable = [];

    public const DIRECTORY = 'competition-videos';

    public const CHUNK_DIRECTORY = 'competition-chunks';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => VideoStatus::class,
            'size' => 'integer',
            'chunk_count' => 'integer',
            'duration_seconds' => 'integer',
        ];
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<CompetitionSubmission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(CompetitionSubmission::class, 'submission_id');
    }

    /**
     * @return BelongsTo<CompetitionQuestion, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(CompetitionQuestion::class, 'question_id');
    }

    public static function disk(): string
    {
        return (string) config('filesystems.private_disk', 'local');
    }

    public static function chunkSize(): int
    {
        return (int) config('competitions.video.chunk_mb') * 1024 * 1024;
    }

    public function chunkDirectory(): string
    {
        return self::CHUNK_DIRECTORY.'/'.$this->uuid;
    }

    /**
     * حجم الجزء المتوقع: كامل إلا الأخير فهو الباقي.
     */
    public function expectedChunkSize(int $index): int
    {
        return $index < $this->chunk_count - 1 ? self::chunkSize() : $this->size - self::chunkSize() * ($this->chunk_count - 1);
    }
}
