<?php

namespace App\Actions\Library;

use App\Enums\LibrarySection;
use App\Models\LibraryItem;
use App\Models\User;
use App\Rules\RealPdf;
use App\Rules\VideoUrl;
use App\Support\PublicImages;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * حفظ عنصر في المكتبة: الصلاحية، ثم مصدر واحد لقسمه، ولا نشر بلا مصدر، ثم فحص الصورة المصغرة
 * والملف الجديدين، ثم الحفظ، ثم حذف ما خرج من العنصر. والعنصر يُسجَّل ويمسح ذاكرة المحتوى
 * بنفسه (LogsActivity وFlushesContentCache).
 */
class SaveLibraryItem
{
    /**
     * أنواع الصوت المقبولة من محتوى الملف، وامتداد كل نوع كما يحفظه الرفع.
     */
    private const AUDIO_EXTENSIONS = ['audio/mpeg' => 'mp3', 'audio/x-m4a' => 'm4a', 'audio/mp4' => 'm4a'];

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, LibraryItem $item, array $data): LibraryItem
    {
        $allowed = $item->exists ? $actor->can('update', $item) : $actor->can('create', LibraryItem::class);

        if (! $allowed) {
            throw new AuthorizationException;
        }

        $previousFile = $item->exists ? $item->file : null;
        $previousSection = $item->exists ? $item->section : null;
        $thumbnailBefore = $item->publicImages()['thumbnail'];

        $item->fill($data);

        // العنصر الجديد قد يصل بلا قسم إن استُدعي الإجراء من غير اللوحة
        if (! $item->getAttribute('section') instanceof LibrarySection) {
            throw ValidationException::withMessages(['section' => __('validation.required', ['attribute' => __('library.fields.section')])]);
        }

        // لكل قسم مصدر واحد، فيُمسح مصدر القسم الآخر: لا ملف لمقطع، ولا رابط لكتاب
        if ($item->section->takesVideoUrl()) {
            $item->file = null;
        } else {
            $item->video_url = null;
        }

        if (filled($item->video_url) && ! VideoUrl::isValid($item->video_url)) {
            throw ValidationException::withMessages(['video_url' => __('validation.video_url')]);
        }

        if ($item->is_published && ! $item->hasSource()) {
            throw ValidationException::withMessages(['is_published' => __('library.validation.needs_source')]);
        }

        // التصنيفات للتصفية بالمطابقة التامة، فلا مسافات زائدة ولا تكرار
        $item->tags = array_values(array_unique(array_filter(array_map('trim', $item->tagList()), fn (string $tag): bool => $tag !== '')));

        $this->checkThumbnail($item, $thumbnailBefore);

        if (filled($item->file) && ($item->file !== $previousFile || $item->section !== $previousSection)) {
            $this->checkFile($item, isNew: $item->file !== $previousFile);
        }

        if (blank($item->file)) {
            $item->file_size = null;
        } elseif ($item->file !== $previousFile) {
            $item->file_size = Storage::disk(LibraryItem::disk())->size((string) $item->file);
        }

        DB::transaction(fn () => $item->save());

        if ($previousFile !== null && $previousFile !== $item->file && self::inDirectory($previousFile)) {
            Storage::disk(LibraryItem::disk())->delete($previousFile);
        }

        PublicImages::discard($thumbnailBefore, $item->publicImages()['thumbnail'], LibraryItem::IMAGE_DIRECTORY);

        return $item;
    }

    /**
     * الصورة الجديدة من مجلد المكتبة، ولا تكون لعنصر آخر (ولو محذوف ناعماً)، فلا يحذفها
     * استبدالها فيه. وتُنظَّف قبل نشرها.
     *
     * @param  list<string>  $before
     *
     * @throws ValidationException
     */
    private function checkThumbnail(LibraryItem $item, array $before): void
    {
        $after = $item->publicImages()['thumbnail'];
        $new = array_values(array_diff($after, $before));

        $inUse = $new !== [] && LibraryItem::withTrashed()
            ->whereIn('thumbnail', $new)
            ->when($item->exists, fn ($query) => $query->whereKeyNot($item->getKey()))
            ->exists();

        if ($inUse) {
            throw ValidationException::withMessages(['thumbnail' => __('validation.image_in_use')]);
        }

        PublicImages::prepare($before, $after, LibraryItem::IMAGE_DIRECTORY, 'thumbnail');
    }

    /**
     * يُفحص الملف المحفوظ نفسه، ولو فُحص عند الرفع، فقد يُستدعى الإجراء من غير اللوحة (الاستيراد).
     * والملف الجديد المرفوض يُحذف، أما ملف العنصر السابق (عند تغيير قسمه) فيبقى له.
     *
     * @throws ValidationException
     */
    private function checkFile(LibraryItem $item, bool $isNew): void
    {
        $path = (string) $item->file;

        // ملف عنصر آخر (ولو محذوف ناعماً) لا يُعاد استعماله، فلا يحذفه استبدالُه فيه
        $inUse = LibraryItem::withTrashed()->where('file', $path)->when($item->exists, fn ($query) => $query->whereKeyNot($item->getKey()))->exists();

        if ($inUse) {
            throw ValidationException::withMessages(['file' => __('validation.file_in_use')]);
        }

        $disk = Storage::disk(LibraryItem::disk());
        $problem = self::inDirectory($path) && $disk->exists($path) ? $this->problem($item, $disk, $path) : 'validation.library_file';

        if ($problem === null) {
            return;
        }

        if ($isNew && self::inDirectory($path)) {
            $disk->delete($path);
        }

        throw ValidationException::withMessages(['file' => __($problem)]);
    }

    /**
     * مفتاح رسالة الخطأ، أو null إن ناسب الملف قسمه: PDF حقيقي للكتب والأبحاث، وصوت حقيقي
     * بامتداد نوعه للصوتيات.
     */
    private function problem(LibraryItem $item, Filesystem $disk, string $path): ?string
    {
        if ($item->section->takesPdf()) {
            return RealPdf::problem((string) $disk->get($path));
        }

        // بداية الملف تكفي لمعرفة نوعه، فلا يُقرأ ملف صوتي كبير كله
        $stream = $disk->readStream($path);
        $head = is_resource($stream) ? (string) fread($stream, 8192) : '';

        if (is_resource($stream)) {
            fclose($stream);
        }

        $type = (string) (new \finfo(FILEINFO_MIME_TYPE))->buffer($head);

        return (self::AUDIO_EXTENSIONS[$type] ?? null) === $item->fileExtension() ? null : 'validation.audio_file';
    }

    private static function inDirectory(string $path): bool
    {
        return str_starts_with($path, LibraryItem::FILE_DIRECTORY.'/') && ! str_contains($path, '..') && ! str_contains($path, "\0");
    }
}
