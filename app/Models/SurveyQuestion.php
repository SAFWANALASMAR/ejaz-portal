<?php

namespace App\Models;

use App\Enums\SurveyQuestionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * سؤال في استبيان. خيارات سؤال الاختيار قائمة نصوص، والإجابة رقم الخيار فيها.
 *
 * @property SurveyQuestionType $type
 * @property array<mixed>|null $options
 */
#[Fillable(['type', 'prompt', 'options', 'is_required', 'sort_order'])]
class SurveyQuestion extends Model
{
    use LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SurveyQuestionType::class,
            'options' => 'array',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Survey, $this>
     */
    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('surveys')->logOnly(['survey_id', 'type', 'prompt', 'options', 'is_required', 'sort_order'])->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * @return list<string>
     */
    public function optionList(): array
    {
        return array_values(array_filter((array) $this->options, fn ($option): bool => is_string($option) && $option !== ''));
    }
}
