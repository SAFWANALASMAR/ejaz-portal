<?php

namespace App\Models;

use Database\Factories\MenuItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * عنصر في القائمة الرئيسية. الرابط إما مسار داخلي يبدأ بـ / أو رابط كامل.
 */
#[Fillable(['parent_id', 'label', 'url', 'opens_in_new_tab', 'is_visible', 'sort'])]
class MenuItem extends Model
{
    /** @use HasFactory<MenuItemFactory> */
    use HasFactory, LogsActivity;

    public const CACHE_KEY = 'site.menu';

    protected static function booted(): void
    {
        // المسح بعد اعتماد المعاملة، وإلا قرأ طلب متزامن القائمة القديمة وخزّنها دائماً
        $flush = fn () => DB::afterCommit(fn () => Cache::forget(self::CACHE_KEY));

        static::saved($flush);
        static::deleted($flush);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opens_in_new_tab' => 'boolean',
            'is_visible' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<MenuItem, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('sort');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
