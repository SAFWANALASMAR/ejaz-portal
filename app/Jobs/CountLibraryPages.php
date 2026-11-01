<?php

namespace App\Jobs;

use App\Models\LibraryItem;
use App\Rules\RealPdf;
use App\Support\LibraryPageCount;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * يحسب عدد صفحات كتاب المكتبة من ملفه ويحفظه في library_items.page_count («قراءاتي» — docs/decisions/2026-11-01-my-reading.md).
 * يُرسل عند حفظ ملف جديد (SaveLibraryItem)، وعند أول بلاغ عن كتاب لم يُحسب عدده (LibraryPageCount::for)، ومهمة واحدة لكل
 * عنصر في وقت واحد. والعدد يُحفظ للملف الذي حُسب منه وحده، فلا يكتب عدد ملف قديم بعد استبداله. وما تعذّر عده يبقى فارغاً.
 */
class CountLibraryPages implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $uniqueFor = 600;

    public function __construct(public readonly int $itemId) {}

    public function uniqueId(): string
    {
        return (string) $this->itemId;
    }

    public function handle(): void
    {
        $item = LibraryItem::query()->find($this->itemId);
        $file = (string) $item?->file;

        if ($item === null || ! $item->section->takesPdf() || $file === '' || ! LibraryItem::isOwnFile($file)) {
            return;
        }

        $disk = Storage::disk(LibraryItem::disk());

        if (! $disk->exists($file) || $disk->size($file) > RealPdf::MAX_FILE_BYTES) {
            return;
        }

        // تحديث بالاستعلام: لا سجل عمليات ولا مسح لذاكرة المحتوى، فالعدد لا يظهر في الصفحات
        LibraryItem::query()->whereKey($item->id)->where('file', $file)
            ->update(['page_count' => LibraryPageCount::count((string) $disk->get($file))]);
    }
}
