<?php

namespace App\Models;

use App\Enums\LibrarySection;
use App\Models\Concerns\FlushesContentCache;
use App\Models\Concerns\Publishable;
use App\Models\Contracts\HasPublicImages;
use App\Rules\VideoUrl;
use Database\Factories\LibraryItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * عنصر في المكتبة: مقطع مرئي برابطه، أو ملف صوتي، أو كتاب أو بحث بملف PDF. الملف في القرص
 * الخاص ولا يُقدَّم إلا منشوراً (LibraryController)، والصورة المصغرة على القرص العام.
 *
 * @property LibrarySection $section
 * @property Carbon|null $published_on
 * @property array<mixed>|null $tags
 */
#[Fillable(['slug', 'title', 'section', 'description', 'author', 'published_on', 'tags', 'thumbnail', 'video_url', 'file', 'file_size', 'is_published', 'on_home'])]
class LibraryItem extends Model implements HasPublicImages
{
    /** @use HasFactory<LibraryItemFactory> */
    use FlushesContentCache, HasFactory, LogsActivity, Publishable, SoftDeletes;

    public const IMAGE_DIRECTORY = 'library';

    public const FILE_DIRECTORY = 'library';

    public const MAX_TAGS = 10;

    public const MAX_TAG_LENGTH = 40;

    /**
     * الملف الصوتي بامتداده (من نوعه الحقيقي عند الرفع)، وأنواعه المقبولة من محتواه. وأولها
     * نوعه عند التقديم.
     */
    public const AUDIO_TYPES = ['mp3' => ['audio/mpeg'], 'm4a' => ['audio/mp4', 'audio/x-m4a']];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'section' => LibrarySection::class,
            'published_on' => 'date',
            'tags' => 'array',
            'file_size' => 'integer',
            'is_published' => 'boolean',
            'on_home' => 'boolean',
        ];
    }

    /**
     * الأحدث نشراً أولاً، وما لا تاريخ له في آخرها. والمعرّف يثبّت الترتيب بين الصفحات.
     *
     * @param  Builder<LibraryItem>  $query
     */
    public function scopeNewestFirst(Builder $query): void
    {
        $query->orderByRaw('published_on desc nulls last')->orderBy('title')->orderBy('id');
    }

    /**
     * قرص الملفات: الخاص من الإعدادات (filesystems.private_disk).
     */
    public static function disk(): string
    {
        return (string) config('filesystems.private_disk', 'local');
    }

    /**
     * للقسم مصدره: رابط المقطع للمرئية، والملف لما سواها. ولا يُنشر عنصر بلا مصدر.
     */
    public function hasSource(): bool
    {
        return $this->section->takesVideoUrl() ? filled($this->video_url) : filled($this->file);
    }

    public function youtubeId(): ?string
    {
        return VideoUrl::youtubeId($this->video_url);
    }

    public function fileExtension(): string
    {
        return strtolower(pathinfo((string) $this->file, PATHINFO_EXTENSION));
    }

    public function fileContentType(): string
    {
        return $this->section->takesPdf() ? 'application/pdf' : self::AUDIO_TYPES[$this->fileExtension()][0] ?? 'application/octet-stream';
    }

    /**
     * اسم الملف عند التنزيل: المعرّف بحروفه اللاتينية، فلا مشكلة ترميز في المتصفحات. والامتداد
     * من القسم للكتب والأبحاث، فلا يصل الزائر ملف PDF باسم آخر.
     */
    public function downloadName(): string
    {
        return $this->slug.'.'.($this->section->takesPdf() ? 'pdf' : $this->fileExtension());
    }

    /**
     * مسار في مجلد ملفات المكتبة، بلا صعود ولا محرف null. فلا يحفظ الإجراء ولا يحذف ولا يقدّم
     * ملفاً غيره.
     */
    public static function isOwnFile(string $path): bool
    {
        return str_starts_with($path, self::FILE_DIRECTORY.'/') && ! str_contains($path, '..') && ! str_contains($path, "\0");
    }

    /**
     * @return list<string>
     */
    public function tagList(): array
    {
        return array_values(array_filter((array) $this->tags, fn ($tag): bool => is_string($tag) && $tag !== ''));
    }

    /**
     * التصنيفات المستعملة في عناصر الاستعلام، الأكثر استعمالاً أولاً.
     *
     * @param  Builder<LibraryItem>  $query
     * @return list<string>
     */
    public static function tagsIn(Builder $query): array
    {
        return $query->get(['tags'])
            ->flatMap(fn (LibraryItem $item): array => $item->tagList())
            ->countBy()
            ->sortDesc()
            ->keys()
            ->map(fn (int|string $tag): string => (string) $tag)
            ->values()
            ->all();
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
        return ['thumbnail' => is_string($this->thumbnail) && $this->thumbnail !== '' ? [$this->thumbnail] : []];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
