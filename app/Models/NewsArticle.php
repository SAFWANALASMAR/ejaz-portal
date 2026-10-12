<?php

namespace App\Models;

use App\Models\Concerns\FlushesContentCache;
use App\Models\Concerns\Publishable;
use App\Models\Contracts\HasPublicImages;
use App\Support\ContentBlocks;
use Database\Factories\NewsArticleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * خبر في المركز الإعلامي: محتواه بالمقاطع المرنة، وغلافه ومعرضه على القرص العام.
 *
 * @property Carbon $published_on
 * @property array<mixed>|null $gallery
 */
#[Fillable(['slug', 'title', 'summary', 'blocks', 'image', 'gallery', 'published_on', 'category', 'is_published'])]
class NewsArticle extends Model implements HasPublicImages
{
    /** @use HasFactory<NewsArticleFactory> */
    use FlushesContentCache, HasFactory, LogsActivity, Publishable;

    public const IMAGE_DIRECTORY = 'news';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'gallery' => 'array',
            'published_on' => 'date',
            'is_published' => 'boolean',
        ];
    }

    /**
     * التصنيف بلا مسافات زائدة، والفارغ بلا تصنيف، فلا تظهر بلاطة فارغة في التصفية.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function category(): Attribute
    {
        return Attribute::make(set: fn (?string $value): ?string => filled($value) ? trim($value) : null);
    }

    /**
     * الأحدث أولاً، وأخبار اليوم الواحد بترتيب إضافتها.
     *
     * @param  Builder<NewsArticle>  $query
     */
    public function scopeNewestFirst(Builder $query): void
    {
        $query->orderByDesc('published_on')->orderByDesc('id');
    }

    /**
     * التصنيفات المستعملة في الأخبار، للتصفية واقتراحها في اللوحة.
     *
     * @param  Builder<NewsArticle>  $query
     * @return list<string>
     */
    public static function categoriesIn(Builder $query): array
    {
        return $query->whereNotNull('category')->distinct()->orderBy('category')->pluck('category')
            ->map(fn (mixed $category): string => (string) $category)
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function galleryImages(): array
    {
        return array_values(array_filter((array) $this->gallery, fn ($path): bool => is_string($path) && $path !== ''));
    }

    public function imageDirectory(): string
    {
        return self::IMAGE_DIRECTORY;
    }

    /**
     * @return array<string, list<string>>
     */
    public function publicImages(): array
    {
        return [
            'image' => is_string($this->image) && $this->image !== '' ? [$this->image] : [],
            'gallery' => $this->galleryImages(),
            'blocks' => ContentBlocks::images($this->blocks),
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
