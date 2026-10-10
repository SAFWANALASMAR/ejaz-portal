<?php

namespace App\Models;

use App\Models\Concerns\FlushesContentCache;
use App\Models\Contracts\HasPublicImages;
use App\Support\ContentBlocks;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * صفحة محتوى بمقاطع مرنة يحررها المحرر من اللوحة. و«عن الجمعية» صفحة بالمعرّف about،
 * لا تُحذف ولا يتغير معرّفها، لأن رابطها مبني عليه.
 */
#[Fillable(['slug', 'title', 'summary', 'blocks', 'is_published'])]
class Page extends Model implements HasPublicImages
{
    /** @use HasFactory<PageFactory> */
    use FlushesContentCache, HasFactory, LogsActivity;

    public const ABOUT = 'about';

    public const IMAGE_DIRECTORY = 'pages';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'is_published' => 'boolean',
        ];
    }

    /**
     * @param  Builder<Page>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
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
        return ['blocks' => ContentBlocks::images($this->blocks)];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
