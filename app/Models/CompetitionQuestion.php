<?php

namespace App\Models;

use App\Enums\QuestionMedia;
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
 * @property QuestionMedia|null $media_type
 * @property array<mixed>|null $options
 */
#[Fillable(['type', 'prompt', 'options', 'correct_option', 'points', 'is_required', 'max_video_seconds', 'max_video_mb', 'sort_order', 'context_text', 'media_type', 'media_path', 'round_id', 'category_id', 'passage_id'])]
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
            'media_type' => QuestionMedia::class,
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
     * مرحلة السؤال في مسابقة الطلاب.
     *
     * @return BelongsTo<CompetitionRound, $this>
     */
    public function round(): BelongsTo
    {
        return $this->belongsTo(CompetitionRound::class, 'round_id');
    }

    /**
     * فئته، أو null لكل الفئات.
     *
     * @return BelongsTo<CompetitionCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(CompetitionCategory::class, 'category_id');
    }

    /**
     * النص أو المقطع الذي يُبنى عليه السؤال.
     *
     * @return BelongsTo<CompetitionPassage, $this>
     */
    public function passage(): BelongsTo
    {
        return $this->belongsTo(CompetitionPassage::class, 'passage_id');
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

    /**
     * مع المسابقة التي يتبعها السؤال، وبلا الخيار الصحيح نفسه: لا يُكشف لمن يطّلع على السجل.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('competitions')->logOnly(['competition_id', 'type', 'prompt', 'options', 'points', 'is_required', 'max_video_seconds', 'max_video_mb', 'sort_order', 'context_text', 'media_type', 'media_path'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
