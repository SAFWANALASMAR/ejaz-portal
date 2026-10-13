<?php

namespace App\Models;

use App\Models\Concerns\FlushesContentCache;
use App\Models\Contracts\HasPublicImages;
use App\Support\ContentBlocks;
use Database\Factories\InitiativeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * مبادرة من مبادرات الجمعية: وصفها بمقاطع مرنة، وأهدافها، ومعرض صورها.
 * والبرامج المرتبطة بها تُضاف مع وحدة البرامج (المرحلة ٣).
 */
#[Fillable(['slug', 'name', 'summary', 'image', 'blocks', 'goals', 'gallery', 'sort', 'is_published'])]
class Initiative extends Model implements HasPublicImages
{
    /** @use HasFactory<InitiativeFactory> */
    use FlushesContentCache, HasFactory, LogsActivity;

    public const IMAGE_DIRECTORY = 'initiatives';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'goals' => 'array',
            'gallery' => 'array',
            'sort' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    /**
     * المنشورة بترتيبها في اللوحة.
     *
     * @param  Builder<Initiative>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true)->orderBy('sort')->orderBy('id');
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
        $paths = fn (mixed $value): array => array_values(array_filter((array) $value, fn ($path) => is_string($path) && $path !== ''));

        return [
            'image' => $paths($this->image),
            'gallery' => $paths($this->gallery),
            'blocks' => ContentBlocks::images($this->blocks),
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
