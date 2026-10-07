<?php

namespace App\Models;

use App\Enums\QuestionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * سؤال في بنك أسئلة المسابقة. خيارات الاختيار من متعدد [{key, label}]، والإجابة تُخزَّن بمفتاح
 * الخيار لا بموضعه، فلا يغيّرها الخلط. والخيار الصحيح مخفي، ولا يصل إلى المتقدم.
 *
 * @property QuestionType $type
 * @property array<mixed>|null $options
 */
#[Fillable(['type', 'prompt', 'options', 'correct_option', 'points', 'is_required', 'max_video_seconds', 'max_video_mb', 'sort_order'])]
#[Hidden(['correct_option'])]
class CompetitionQuestion extends Model
{
    use LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'options' => 'array',
            'points' => 'integer',
            'is_required' => 'boolean',
            'max_video_seconds' => 'integer',
            'max_video_mb' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Competition, $this>
     */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public function optionList(): array
    {
        return array_values(array_filter((array) $this->options, fn ($option): bool => is_array($option) && isset($option['key'], $option['label'])));
    }

    /**
     * @return list<string>
     */
    public function optionKeys(): array
    {
        return array_column($this->optionList(), 'key');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('competitions')->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
