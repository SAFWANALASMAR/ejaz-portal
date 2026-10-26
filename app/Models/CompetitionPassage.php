<?php

namespace App\Models;

use App\Enums\PassageKind;
use App\Rules\VideoUrl;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * نص مقروء أو مقطع يوتيوب في مرحلة من مسابقة الطلاب، تجتمع تحته أسئلته في الاختبار ولا تتفرق بالخلط.
 *
 * @property PassageKind $kind
 */
#[Fillable(['round_id', 'category_id', 'kind', 'title', 'body', 'video_url'])]
class CompetitionPassage extends Model
{
    use LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => PassageKind::class,
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
     * @return BelongsTo<CompetitionRound, $this>
     */
    public function round(): BelongsTo
    {
        return $this->belongsTo(CompetitionRound::class, 'round_id');
    }

    /**
     * @return BelongsTo<CompetitionCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(CompetitionCategory::class, 'category_id');
    }

    /**
     * @return HasMany<CompetitionQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(CompetitionQuestion::class, 'passage_id')->orderBy('sort_order')->orderBy('id');
    }

    public function youtubeId(): ?string
    {
        return $this->kind === PassageKind::Video ? VideoUrl::youtubeId($this->video_url) : null;
    }

    public function label(): string
    {
        return filled($this->title) ? (string) $this->title : mb_strimwidth(trim((string) ($this->body ?? $this->video_url)), 0, 60, '…');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('competitions')->logOnly(['competition_id', 'round_id', 'category_id', 'kind', 'title', 'body', 'video_url'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
