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
 * نوع برامج (بحثية، تدريبية، علمية...) يُدار من اللوحة، ويظهر تبويباً في «برامجنا».
 * لا يُحذف نوع له برامج (قاعدة البيانات ترفضه).
 */
#[Fillable(['name', 'slug', 'sort'])]
class ProgramType extends Model
{
    /** @use HasFactory<ProgramTypeFactory> */
    use FlushesContentCache, HasFactory, LogsActivity;

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
