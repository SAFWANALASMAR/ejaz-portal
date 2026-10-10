<?php

namespace App\Models;

use App\Enums\WorkType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * عمل مرفوع في مشاركة: صورة (أُعيد ترميزها) أو PDF حقيقي في القرص الخاص بمسار عشوائي، أو رابط https.
 * ولا يُقدَّم الملف إلا بعد فحص الصلاحية (WorkFileController).
 *
 * @property WorkType $type
 */
class SubmissionWork extends Model
{
    use HasUuids;

    /**
     * لا تعبئة جماعية: كل الحقول يضبطها الإجراء نفسه.
     *
     * @var list<string>
     */
    protected $fillable = [];

    public const DIRECTORY = 'competition-works';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => WorkType::class,
            'size' => 'integer',
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

    public static function disk(): string
    {
        return SubmissionVideo::disk();
    }
}
