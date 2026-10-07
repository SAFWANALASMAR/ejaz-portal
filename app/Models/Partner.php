<?php

namespace App\Models;

use App\Models\Concerns\FlushesContentCache;
use App\Models\Contracts\HasPublicImages;
use Database\Factories\PartnerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * شريك للجمعية: اسمه وشعاره ورابطه، بترتيبه في اللوحة. يظهر في المركز الإعلامي والرئيسية.
 */
#[Fillable(['name', 'logo', 'url', 'sort', 'is_published'])]
class Partner extends Model implements HasPublicImages
{
    /** @use HasFactory<PartnerFactory> */
    use FlushesContentCache, HasFactory, LogsActivity;

    public const IMAGE_DIRECTORY = 'partners';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    /**
     * المنشورون بترتيبهم في اللوحة.
     *
     * @param  Builder<Partner>  $query
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
        return ['logo' => is_string($this->logo) && $this->logo !== '' ? [$this->logo] : []];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
