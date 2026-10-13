<?php

namespace App\Models;

use App\Models\Concerns\FlushesContentCache;
use App\Models\Concerns\Publishable;
use App\Models\Contracts\HasPublicImages;
use Database\Factories\AlbumFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * ألبوم في مكتبة الصور: صوره على القرص العام بترتيبها، وأولها غلافه.
 *
 * @property Carbon $published_on
 * @property array<mixed>|null $photos
 */
#[Fillable(['slug', 'title', 'description', 'photos', 'published_on', 'is_published'])]
class Album extends Model implements HasPublicImages
{
    /** @use HasFactory<AlbumFactory> */
    use FlushesContentCache, HasFactory, LogsActivity, Publishable;

    public const IMAGE_DIRECTORY = 'albums';

    public const MAX_PHOTOS = 60;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'photos' => 'array',
            'published_on' => 'date',
            'is_published' => 'boolean',
        ];
    }

    /**
     * @param  Builder<Album>  $query
     */
    public function scopeNewestFirst(Builder $query): void
    {
        $query->orderByDesc('published_on')->orderByDesc('id');
    }

    /**
     * @return list<string>
     */
    public function photoList(): array
    {
        return array_values(array_filter((array) $this->photos, fn ($path): bool => is_string($path) && $path !== ''));
    }

    public function cover(): ?string
    {
        return $this->photoList()[0] ?? null;
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
        return ['photos' => $this->photoList()];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
