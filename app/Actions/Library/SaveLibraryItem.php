<?php

namespace App\Actions\Library;

use App\Enums\LibrarySection;
use App\Jobs\CountLibraryPages;
use App\Jobs\FetchYoutubeThumbnail;
use App\Jobs\MeasureLibraryDuration;
use App\Models\LibraryItem;
use App\Models\User;
use App\Rules\RealPdf;
use App\Rules\VideoUrl;
use App\Support\PublicImages;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Arr;
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
     * @param  array<string, mixed>  $data
     * @param  bool  $legacyFile  ملف صوتي من الموقع السابق (ImportLegacyContent)، فيُقبل مع قرار الروابط
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, LibraryItem $item, array $data, bool $legacyFile = false): LibraryItem
    {
        $allowed = $item->exists ? $actor->can('update', $item) : $actor->can('create', LibraryItem::class);

        if (! $allowed) {
            throw new AuthorizationException;
        }

        $previousFile = $item->exists ? $item->file : null;
        $previousSection = $item->exists ? $item->section : null;
        $thumbnailBefore = $item->publicImages()['thumbnail'];

        // القسم من قيمه الأربع، ولو استُدعي الإجراء من غير اللوحة بقيمة أخرى أو بلا قسم
        $section = $data['section'] ?? $item->getAttribute('section');
        $section = $section instanceof LibrarySection ? $section : LibrarySection::tryFrom(is_string($section) ? $section : '');

        if ($section === null) {
            throw ValidationException::withMessages(['section' => __('validation.enum', ['attribute' => __('library.fields.section')])]);
        }

        // ومدة الصوت والمرئي عدد ثوانٍ صحيح لكل مستدعٍ، وللكتب لا مدة (برامج المكتبة)
        $duration = $data['duration_seconds'] ?? null;

        if ($duration !== null && (filter_var($duration, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 86400]]) === false)) {
            throw ValidationException::withMessages(['duration_seconds' => __('validation.integer', ['attribute' => __('library.fields.duration_seconds')])]);
        }

        if (array_key_exists('duration_seconds', $data)) {
            $data['duration_seconds'] = $duration === null || ! $section->takesVideoUrl() ? null : (int) $duration;
        }

        // وحجم الملف يُحسب من الملف نفسه، لا مما يُمرَّر
        $item->fill([...Arr::except($data, ['file_size']), 'section' => $section]);

        // المرئية والصوتية برابط يوتيوب لا بملف مرفوع (ملاحظات المدير التنفيذي، 25 أكتوبر 2026): لا يُرفع ملف صوتي جديد،
        // ويبقى ما رُفع منها قبل القرار ما لم يُضف رابط يحل محله. ومع الرابط يُمسح الملف بعده، فلا رفض (كتاب صار صوتية)
        if (! $legacyFile && $item->section->keepsLegacyFile() && filled($item->file) && blank($item->video_url)
            && ($item->file !== $previousFile || $previousSection !== $item->section)) {
            throw ValidationException::withMessages(['file' => __('library.validation.audio_by_link')]);
        }

        // لكل قسم مصدر واحد، فيُمسح مصدر القسم الآخر: لا ملف لمقطع، ولا رابط لكتاب
        if ($item->section->takesVideoUrl()) {
            if (! $item->section->keepsLegacyFile() || filled($item->video_url)) {
                $item->file = null;
            }
        } else {
            $item->video_url = null;
        }

        // الرئيسية تعرض مقاطع يوتيوب وحدها، فلا تبقى العلامة على غيرها (ولو تغيّر القسم أو الرابط)
        if ($item->youtubeId() === null) {
            $item->on_home = false;
        }

        // «للقراءة فقط» للكتب والأبحاث وحدها، فلا تبقى العلامة على صوت أو مرئي (ولو تغيّر القسم)
        if (! $item->section->takesPdf() || $item->getAttribute('is_downloadable') === null) {
            $item->is_downloadable = true;
        }

        if (filled($item->video_url) && ! VideoUrl::isValid($item->video_url)) {
            throw ValidationException::withMessages(['video_url' => __('validation.video_url')]);
        }

        if ($item->is_published && ! $item->hasSource()) {
            throw ValidationException::withMessages(['is_published' => __('library.validation.needs_source')]);
        }

        // التصنيفات للتصفية بالمطابقة التامة، فلا مسافات زائدة ولا تكرار. وحدودها حدود النموذج نفسها،
        // فلا يتجاوزها مستدعٍ من غير اللوحة فيثقل استعلام العناصر ذات الصلة
        $tags = array_values(array_unique(array_filter(array_map('trim', $item->tagList()), fn (string $tag): bool => $tag !== '')));

        if (count($tags) > LibraryItem::MAX_TAGS || array_filter($tags, fn (string $tag): bool => mb_strlen($tag) > LibraryItem::MAX_TAG_LENGTH) !== []) {
            throw ValidationException::withMessages(['tags' => __('library.validation.tags', ['count' => LibraryItem::MAX_TAGS, 'length' => LibraryItem::MAX_TAG_LENGTH])]);
        }

        $item->tags = $tags;

        $this->checkThumbnail($item, $thumbnailBefore);

        if (filled($item->file) && ($item->file !== $previousFile || $item->section !== $previousSection)) {
            $this->checkFile($item, isNew: $item->file !== $previousFile);
        }

        if (blank($item->file)) {
            $item->file_size = null;
        } elseif ($item->file !== $previousFile) {
            $item->file_size = Storage::disk(LibraryItem::disk())->size((string) $item->file);
        }

        // عدد صفحات الملف السابق لا يبقى للجديد، ويُحسب للجديد في الطابور بعد الحفظ («قراءاتي»)
        $countPages = $item->file !== $previousFile || $item->section !== $previousSection;

        if ($countPages) {
            $item->forceFill(['page_count' => null, 'page_count_checked_at' => null]);
        }

        // صورة يوتيوب التي جلبها الخادم لمقطع آخر لا تبقى بعد تغيّر الرابط (FetchYoutubeThumbnail::isFetchedFor)
        if (filled($item->thumbnail) && FetchYoutubeThumbnail::isFetched((string) $item->thumbnail)
            && ! FetchYoutubeThumbnail::isFetchedFor((string) $item->thumbnail, $item->youtubeId())) {
            $item->thumbnail = null;
        }

        DB::transaction(fn () => $item->save());

        if ($previousFile !== null && $previousFile !== $item->file && LibraryItem::isOwnFile($previousFile)) {
            Storage::disk(LibraryItem::disk())->delete($previousFile);
        }

        PublicImages::discard($thumbnailBefore, $item->publicImages()['thumbnail'], LibraryItem::IMAGE_DIRECTORY);

        if (blank($item->thumbnail) && $item->youtubeId() !== null && config('security.youtube_thumbnails')) {
            FetchYoutubeThumbnail::dispatch($item->getKey());
        }

        if ($countPages && $item->section->takesPdf() && filled($item->file)) {
            CountLibraryPages::dispatch($item->getKey(), (string) $item->file);
        }

        // ومدة ملف الصوت من الخادم لبرامج الاستماع (docs/decisions/2026-11-02-reading-program.md)
        if ($countPages && $item->section === LibrarySection::Audio && filled($item->file)) {
            MeasureLibraryDuration::dispatch($item, (string) $item->file);
        }

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
        $problem = LibraryItem::isOwnFile($path) && $disk->exists($path) ? $this->problem($item, $disk, $path) : 'validation.library_file';

        if ($problem === null) {
            return;
        }

        if ($isNew && LibraryItem::isOwnFile($path)) {
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
        // والامتداد pdf، فيُنزَّل باسمه الصحيح
        if ($item->section->takesPdf()) {
            return $item->fileExtension() === 'pdf' ? RealPdf::problem((string) $disk->get($path)) : 'validation.real_pdf';
        }

        // بداية الملف تكفي لمعرفة نوعه، فلا يُقرأ ملف صوتي كبير كله
        $stream = $disk->readStream($path);
        $head = is_resource($stream) ? (string) fread($stream, 8192) : '';

        if (is_resource($stream)) {
            fclose($stream);
        }

        $type = (string) (new \finfo(FILEINFO_MIME_TYPE))->buffer($head);

        return in_array($type, LibraryItem::AUDIO_TYPES[$item->fileExtension()] ?? [], true) ? null : 'validation.audio_file';
    }
}
