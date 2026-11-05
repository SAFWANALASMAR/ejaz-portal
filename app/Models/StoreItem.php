<?php

namespace App\Models;

use App\Enums\StoreItemCategory;
use App\Models\Concerns\FlushesContentCache;
use App\Models\Concerns\Publishable;
use App\Models\Contracts\HasPublicImages;
use App\Rules\StoreUrl;
use App\Support\Store\StoreProduct;
use Database\Factories\StoreItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * عنصر في صفحة «المتجر والتبرعات» (قرار صفوان، 4 نوفمبر 2026): تبرع أو إصدار، بعنوانه ووصفه القصير وصورته، ورابطه في متجر
 * الجمعية على سلة حيث يتم الدفع. والرابط https من نطاقات المتجر المسموحة وحدها (StoreUrl)، ويُفحص عند العرض أيضاً.
 *
 * @property StoreItemCategory $category
 */
#[Fillable(['title', 'description', 'image', 'category', 'salla_url', 'salla_product_id', 'sort', 'is_published'])]
class StoreItem extends Model implements HasPublicImages
{
    /** @use HasFactory<StoreItemFactory> */
    use FlushesContentCache, HasFactory, LogsActivity, Publishable, SoftDeletes;

    public const IMAGE_DIRECTORY = 'store-items';

    /**
     * الحقول التي لا تُنشر وفيها علامة النص الناقص (Publishable).
     *
     * @var list<string>
     */
    protected array $missingTextFields = ['title', 'description'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => StoreItemCategory::class,
            'sort' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    /**
     * بترتيبها في اللوحة.
     *
     * @param  Builder<StoreItem>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort')->orderBy('id');
    }

    /**
     * العنصر كما يعرضه كتالوج المتجر، والرابط الذي خرج عن نطاقات المتجر لا يُعرض.
     */
    public function toProduct(): ?StoreProduct
    {
        if (! StoreUrl::isAllowed($this->salla_url)) {
            return null;
        }

        return new StoreProduct(
            $this->title,
            $this->description,
            $this->salla_url,
            image: filled($this->image) ? Storage::disk('public')->url($this->image) : null,
            category: $this->category,
            sallaProductId: filled($this->salla_product_id) ? $this->salla_product_id : null,
        );
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
        return ['image' => filled($this->image) ? [$this->image] : []];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
