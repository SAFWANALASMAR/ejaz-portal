<?php

namespace App\Models;

use App\Models\Concerns\FlushesContentCache;
use App\Models\Contracts\HasPublicImages;
use Database\Factories\BoardMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * عضو مجلس الإدارة في صفحة «عن الجمعية». الأسماء والصور تُدخل بموافقة أصحابها.
 */
#[Fillable(['name', 'position', 'photo', 'sort', 'is_visible'])]
class BoardMember extends Model implements HasPublicImages
{
    /** @use HasFactory<BoardMemberFactory> */
    use FlushesContentCache, HasFactory, LogsActivity;

    public const IMAGE_DIRECTORY = 'board';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort' => 'integer',
            'is_visible' => 'boolean',
        ];
    }

    /**
     * الظاهرون بترتيبهم في اللوحة.
     *
     * @param  Builder<BoardMember>  $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->where('is_visible', true)->orderBy('sort')->orderBy('id');
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
        return ['photo' => is_string($this->photo) && $this->photo !== '' ? [$this->photo] : []];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
