<?php

namespace App\Models;

use App\Enums\LibrarySection;
use App\Models\Concerns\FlushesContentCache;
use App\Models\Concerns\Publishable;
use App\Models\Contracts\HasPublicImages;
use App\Rules\VideoUrl;
use App\Support\StoragePath;
use Database\Factories\LibraryItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * عنصر في المكتبة: مقطع مرئي برابطه، أو ملف صوتي، أو كتاب أو بحث بملف PDF. الملف في القرص
 * الخاص ولا يُقدَّم إلا منشوراً (LibraryController)، والصورة المصغرة على القرص العام.
 *
 * @property LibrarySection $section
 * @property Carbon|null $published_on
 * @property array<mixed>|null $tags
 * @property int|null $duration_seconds
 * @property bool $is_downloadable
 * @property int|null $page_count عدد صفحات الكتاب من ملفه (CountLibraryPages)، وفارغ حتى يُحسب أو إن تعذّر
 * @property Carbon|null $page_count_checked_at وقت محاولة عدّ الملف الحالي، فلا تُعاد مع كل بلاغ
 */
#[Fillable(['slug', 'title', 'section', 'description', 'author', 'published_on', 'tags', 'thumbnail', 'video_url', 'file', 'file_size', 'is_published', 'on_home', 'is_downloadable', 'duration_seconds'])]
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
            'is_downloadable' => 'boolean',
            'page_count' => 'integer',
            'duration_seconds' => 'integer',
            'page_count_checked_at' => 'datetime',
        ];
    }

    /**
     * كتاب أو بحث «للقراءة فقط — بلا تحميل»: يُعرض في قارئ الصفحة وحده، بلا تنزيل ولا فتح للملف نفسه
     * (LibraryController). والخيار للكتب والأبحاث وحدها، فالصوت والمرئي لا يأخذانه.
     */
    public function isReadOnly(): bool
    {
        return $this->section->takesPdf() && $this->is_downloadable === false;
    }

    /**
     * «قراءاتي» (docs/decisions/2026-11-01-my-reading.md): الصفحات تُحصى في قارئ الصفحة وحده (كتاب «للقراءة فقط»)، أما
     * الكتاب القابل للتنزيل فيُعرض في عارض المتصفح ولا يُعرف ما قُرئ منه، فعلامة «فتحته» وحدها.
     */
    public function tracksPages(): bool
    {
        return $this->isReadOnly() && filled($this->file);
    }

    /**
     * والاستماع والمشاهدة بالثواني في مشغّل الصفحة: المقطع من ملف بنطاق معتمد، والصوت القديم بملفه. ومقطع يوتيوب لا تُقرأ
     * مدته بلا سكربت يوتيوب، وسياسة المحتوى لا تسمح به، فعلامة «فتحته» وحدها.
     */
    public function tracksPlayback(): bool
    {
        return match ($this->section) {
            LibrarySection::Videos => filled($this->video_url) && $this->youtubeId() === null,
            LibrarySection::Audio => filled($this->file),
            default => false,
        };
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
     * للقسم مصدره: رابط المقطع للمرئية والصوتية، والملف للكتب والأبحاث، والصوتية القديمة بملفها. ولا يُنشر عنصر بلا مصدر.
     */
    public function hasSource(): bool
    {
        if ($this->section->takesVideoUrl()) {
            return filled($this->video_url) || ($this->section->keepsLegacyFile() && filled($this->file));
        }

        return filled($this->file);
    }

    public function youtubeId(): ?string
    {
        return VideoUrl::youtubeId($this->video_url);
    }

    /**
     * الصورة المصغرة من القرص العام: المرفوعة، أو صورة مقطع يوتيوب التي جلبها الخادم (FetchYoutubeThumbnail)، فلا يتصل
     * متصفح الزائر بجوجل قبل ضغطه (قرار 7 أكتوبر 2026). وإلا لا شيء.
     */
    public function thumbnailUrl(): ?string
    {
        return filled($this->thumbnail) ? Storage::disk('public')->url($this->thumbnail) : null;
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
        return StoragePath::inDirectory($path, self::FILE_DIRECTORY);
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
