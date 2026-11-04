<?php

namespace App\Models;

use App\Enums\SocialPlatform;
use App\Enums\SocialPostKind;
use App\Models\Concerns\FlushesContentCache;
use App\Models\Concerns\Publishable;
use App\Models\Contracts\HasPublicImages;
use App\Rules\SocialPostUrl;
use Database\Factories\SocialPostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * منشور أو حدث للجمعية على منصة تواصل، في «منشوراتنا» بالمركز الإعلامي (طلب صفوان، 3 نوفمبر 2026): نصه وتاريخه ورابطه
 * الأصلي وصورة اختيارية على القرص العام. لا تضمين ولا سكربت من المنصة، ولا اتصال من الخادم بها: الرابط يُفتح في تبويب جديد.
 *
 * @property SocialPlatform $platform
 * @property SocialPostKind $kind
 * @property Carbon $posted_on
 */
#[Fillable(['platform', 'kind', 'body', 'posted_on', 'url', 'image', 'source_image_url', 'is_published'])]
class SocialPost extends Model implements HasPublicImages
{
    /** @use HasFactory<SocialPostFactory> */
    use FlushesContentCache, HasFactory, HasUuids, LogsActivity, Publishable, SoftDeletes;

    public const IMAGE_DIRECTORY = 'social-posts';

    /**
     * الحقول التي لا تُنشر وفيها علامة النص الناقص (Publishable).
     *
     * @var list<string>
     */
    protected array $missingTextFields = ['body'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform' => SocialPlatform::class,
            'kind' => SocialPostKind::class,
            'posted_on' => 'date',
            'is_published' => 'boolean',
        ];
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * الرابط بلا مسافات ولا شرطة مائلة في آخره، فلا يتكرر المنشور نفسه بفرق كتابة.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function url(): Attribute
    {
        return Attribute::make(set: fn (?string $value): ?string => $value === null ? null : SocialPostUrl::normalize($value));
    }

    /**
     * الأحدث أولاً، ومنشورات اليوم الواحد بترتيب إضافتها.
     *
     * @param  Builder<SocialPost>  $query
     */
    public function scopeNewestFirst(Builder $query): void
    {
        $query->orderByDesc('posted_on')->orderByDesc('id');
    }

    /**
     * الرابط الأصلي إن بقي على نطاق منصته، ويُفحص عند العرض أيضاً.
     */
    public function href(): ?string
    {
        return SocialPostUrl::matches($this->url, $this->platform) ? $this->url : null;
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
