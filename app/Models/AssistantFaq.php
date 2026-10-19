<?php

namespace App\Models;

use App\Enums\AssistantReviewStatus;
use Database\Factories\AssistantFaqFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * سؤال شائع تراجعه الجمعية، يقدّمه المساعد على غيره متى اعتُمد (requirements/10). والمصدر مسار في البوابة.
 *
 * @property string $question
 * @property string $answer
 * @property string|null $keywords
 * @property string $source_url
 * @property AssistantReviewStatus $status
 * @property int $sort
 */
#[Fillable(['question', 'answer', 'keywords', 'source_url', 'status', 'sort'])]
class AssistantFaq extends Model
{
    /** @use HasFactory<AssistantFaqFactory> */
    use HasFactory, LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AssistantReviewStatus::class,
            'sort' => 'integer',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', AssistantReviewStatus::Approved->value);
    }

    public function isActive(): bool
    {
        return $this->status === AssistantReviewStatus::Approved;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('assistant')->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
