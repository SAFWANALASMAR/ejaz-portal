<?php

namespace App\Models;

use App\Enums\AnnouncementKind;
use App\Models\Concerns\FlushesContentCache;
use App\Models\Contracts\HasPublicImages;
use App\Rules\SafeUrl;
use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * إعلان في شريط الرئيسية وصفحة الإعلانات (ملاحظات المدير التنفيذي، 25 أكتوبر 2026): صورة وعنوان ورابط اختياري، يظهر
 * منشوراً بين يومي البداية والنهاية بتوقيت الرياض. وإعلان المتجر يفتح متجر الجمعية برابطه من الإعدادات.
 *
 * @property AnnouncementKind $kind
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 */
#[Fillable(['title', 'image', 'url', 'kind', 'starts_on', 'ends_on', 'sort', 'is_published'])]
class Announcement extends Model implements HasPublicImages
{
    /** @use HasFactory<AnnouncementFactory> */
    use FlushesContentCache, HasFactory, LogsActivity;

    public const IMAGE_DIRECTORY = 'announcements';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => AnnouncementKind::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'sort' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    /**
     * المنشور في مدة عرضه اليوم، بترتيبه في اللوحة.
     *
     * @param  Builder<Announcement>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $today = now(config('app.display_timezone'))->toDateString();

        $query->where('is_published', true)
            ->where(fn (Builder $query) => $query->whereNull('starts_on')->orWhere('starts_on', '<=', $today))
            ->where(fn (Builder $query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', $today))
            ->orderBy('sort')
            ->orderBy('id');
    }

    /**
     * رابط الإعلان: رابطه الآمن، وإلا المتجر من الإعدادات لإعلان المتجر، وإلا لا رابط. ويُفحص عند العرض أيضاً.
     */
    public function href(): ?string
    {
        if (is_string($this->url) && $this->url !== '' && SafeUrl::isSafe($this->url)) {
            return $this->url;
        }

        return $this->kind === AnnouncementKind::Store ? route('store.index') : null;
    }

    /**
     * يفتح في تبويب جديد ما كان رابطاً كاملاً، ومنه المتجر (/store يحوّل إليه). والمسار الداخلي (/…) في الصفحة نفسها.
     */
    public function opensExternally(): bool
    {
        $href = $this->href();

        return $href !== null && ! str_starts_with($href, '/');
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
