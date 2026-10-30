<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * فتح لمعرض الشواهد برابطه السري: الوقت والشبكة المقنّعة وحدهما (NetworkPrefix::masked)، بلا عنوان كامل ولا متصفح.
 * ويُحذف بعد مدة الاحتفاظ بسجل العمليات نفسها (activitylog.clean_after_days — model:prune).
 *
 * @property string $network
 * @property Carbon $visited_at
 */
#[Fillable(['network', 'visited_at'])]
class EvidenceGalleryVisit extends Model
{
    use MassPrunable;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visited_at' => 'datetime',
        ];
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('visited_at', '<', now()->subDays((int) config('activitylog.clean_after_days')));
    }

    /**
     * @return BelongsTo<EvidenceGallery, $this>
     */
    public function gallery(): BelongsTo
    {
        return $this->belongsTo(EvidenceGallery::class, 'evidence_gallery_id');
    }
}
