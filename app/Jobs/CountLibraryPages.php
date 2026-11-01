<?php

namespace App\Jobs;

use App\Actions\Library\ClipLibraryProgress;
use App\Models\LibraryItem;
use App\Rules\RealPdf;
use App\Support\LibraryPageCount;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * يحسب عدد صفحات كتاب المكتبة من ملفه ويحفظه في library_items.page_count («قراءاتي» — docs/decisions/2026-11-01-my-reading.md).
 * - يُرسل عند حفظ ملف جديد (SaveLibraryItem)، وعند أول بلاغ عن كتاب لم يُحاول عدّه قط (LibraryPageCount::for)، ومهمة واحدة لكل
 *   عنصر في وقت واحد.
 * - ويحفظ وقت المحاولة (page_count_checked_at) نجح العد أو تعذّر، فلا يُعاد لكتاب تعذّر عدّه إلا عند تغيّر ملفه (م١).
 * - والعدد يُكتب للملف الذي حُسب منه وحده، فلا يكتب عدد ملف قديم بعد استبداله. ثم يُقصّ تقدّم القرّاء إلى العدد (م٢).
 * - في طابور مستقل (library) بمهلة صريحة، فلا يحجب البريد والإشعارات في default (supervisor-library في config/horizon.php).
 */
class CountLibraryPages implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const QUEUE = 'library';

    public int $tries = 1;

    public int $timeout = 120;

    public int $uniqueFor = 600;

    /**
     * @param  string  $file  الملف المطلوب عدّه: به تتفرد المهمة، فلا يحجب قفلُ ملفٍ سابق عدَّ ملف جديد
     */
    public function __construct(public readonly int $itemId, public readonly string $file = '')
    {
        $this->onQueue(self::QUEUE);
    }

    public function uniqueId(): string
    {
        return $this->itemId.':'.sha1($this->file);
    }

    public function handle(ClipLibraryProgress $clip): void
    {
        $item = LibraryItem::query()->find($this->itemId);
        $file = (string) $item?->file;

        if ($item === null || ! $item->section->takesPdf() || $file === '' || ! LibraryItem::isOwnFile($file)) {
            return;
        }

        $disk = Storage::disk(LibraryItem::disk());
        $count = $disk->exists($file) && $disk->size($file) <= RealPdf::MAX_FILE_BYTES
            ? LibraryPageCount::count((string) $disk->get($file))
            : null;

        // تحديث بالاستعلام: لا سجل عمليات ولا مسح لذاكرة المحتوى، فالعدد لا يظهر في الصفحات
        $saved = LibraryItem::query()->whereKey($item->id)->where('file', $file)
            ->update(['page_count' => $count, 'page_count_checked_at' => now()]);

        if ($saved === 1 && $count !== null) {
            $clip->handle($item->id, $count);
        }
    }
}
