<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * إعداد واحد بقيمة JSON. القراءة من الذاكرة المؤقتة، وتُمسح عند أي تعديل.
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    use LogsActivity;

    protected static function booted(): void
    {
        // المسح بعد اعتماد المعاملة، وإلا قرأ طلب متزامن القيمة القديمة وخزّنها دائماً
        $flush = fn (Setting $setting) => DB::afterCommit(fn () => Cache::forget(self::cacheKey($setting->key)));

        static::saved($flush);
        static::deleted($flush);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    public static function cacheKey(string $key): string
    {
        return 'settings.'.$key;
    }

    /**
     * @return array<string, mixed>
     */
    public static function group(string $key): array
    {
        return Cache::rememberForever(
            self::cacheKey($key),
            fn (): array => static::query()->where('key', $key)->value('value') ?? [],
        );
    }

    /**
     * @param  array<string, mixed>  $value
     */
    public static function put(string $key, array $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['key', 'value'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
