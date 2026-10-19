<?php

namespace App\Models;

use App\Enums\AssistantReviewStatus;
use App\Support\Assistant\AssistantWidget;
use Database\Factories\AssistantGuideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * مقالة من «دليل استخدام البوابة»: خطوات مرقمة لعمل في الموقع ورابط صفحته (اسم مسار بلا معاملات). مسوداتها من
 * الشيفرة (database/data/assistant-guide.php)، ويفعّلها المسؤول للمساعد بعد مراجعتها، وتعتمدها الجمعية.
 *
 * @property string $slug
 * @property string $title
 * @property string|null $keywords
 * @property array<mixed> $steps
 * @property string $route_name
 * @property AssistantReviewStatus $status
 * @property int $sort
 */
#[Fillable(['slug', 'title', 'keywords', 'steps', 'route_name', 'status', 'sort'])]
class AssistantGuide extends Model
{
    /** @use HasFactory<AssistantGuideFactory> */
    use HasFactory, LogsActivity;

    protected static function booted(): void
    {
        $flush = fn () => DB::afterCommit(fn () => Cache::forget(AssistantWidget::SUGGESTIONS_KEY));

        static::saved($flush);
        static::deleted($flush);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'steps' => 'array',
            'status' => AssistantReviewStatus::class,
            'sort' => 'integer',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', AssistantReviewStatus::guideActive());
    }

    public function isActive(): bool
    {
        return in_array($this->status->value, AssistantReviewStatus::guideActive(), true);
    }

    /**
     * @return list<string>
     */
    public function stepList(): array
    {
        return array_values(array_filter((array) $this->steps, fn ($step): bool => is_string($step) && trim($step) !== ''));
    }

    /**
     * مسار الصفحة النسبي، أو null إن لم يعد المسار موجوداً.
     */
    public function path(): ?string
    {
        return Route::has($this->route_name) ? route($this->route_name, [], false) : null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('assistant')->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
