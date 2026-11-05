<?php

namespace App\Models;

use App\Models\Concerns\FlushesContentCache;
use Database\Factories\ProgramTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * نوع برامج يُدار من اللوحة، ويظهر تبويباً في «الدورات والبرامج».
 * لا يُحذف نوع له برامج (قاعدة البيانات ترفضه).
 */
#[Fillable(['name', 'slug', 'sort'])]
class ProgramType extends Model
{
    /** @use HasFactory<ProgramTypeFactory> */
    use FlushesContentCache, HasFactory, LogsActivity;

    /**
     * أيقونة كل نوع في شريط «الدورات والبرامج» (ملاحظات المدير التنفيذي، 4 نوفمبر 2026): الأنواع المبذورة (ProgramTypesSeeder)،
     * وما أضافته اللوحة بعدها بالأيقونة العامة.
     */
    public const ICONS = [
        'training-courses' => 'academic-cap',
        'scientific-councils' => 'building-library',
        'book-reading' => 'book-open',
        'audio-listening' => 'musical-note',
        'video-watching' => 'play-circle',
    ];

    public function icon(): string
    {
        return self::ICONS[$this->slug] ?? 'squares-2x2';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sort' => 'integer'];
    }

    /**
     * @return HasMany<Program, $this>
     */
    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }

    /**
     * @param  Builder<ProgramType>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort')->orderBy('id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
