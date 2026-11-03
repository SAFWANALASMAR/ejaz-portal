<?php

namespace App\Jobs;

use App\Enums\LibrarySection;
use App\Models\LibraryItem;
use App\Support\AudioProbe;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * مدة ملف الصوت في المكتبة من الملف نفسه على الخادم (AudioProbe)، فيُحسب إتمام برنامج الاستماع بها لا بمدة يرسلها المتصفح
 * (docs/decisions/2026-11-02-reading-program.md). على طابور المكتبة كعدّ صفحات الكتب، ومهمة واحدة لكل ملف. والمدة تُكتب للملف
 * الذي قيس منه وحده، فلا يكتب ملف قديم بعد استبداله. وما تعذّر قياسه يبقى بمدة المسؤول إن أدخلها.
 */
class MeasureLibraryDuration implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 150;

    public function __construct(public LibraryItem $item, public string $file)
    {
        $this->onQueue('library');
    }

    public function uniqueId(): string
    {
        return $this->item->getKey().':'.$this->file;
    }

    public function handle(): void
    {
        $item = $this->item->fresh();

        if ($item === null || $item->section !== LibrarySection::Audio || $item->file !== $this->file) {
            return;
        }

        $source = Storage::disk(LibraryItem::disk())->readStream($this->file);
        $local = tmpfile();

        if ($source === null || $local === false) {
            return;
        }

        try {
            stream_copy_to_stream($source, $local);
            rewind($local);
            $seconds = AudioProbe::seconds($local);
        } finally {
            fclose($source);
            fclose($local);
        }

        if ($seconds !== null) {
            LibraryItem::query()->whereKey($item->id)->where('file', $this->file)->update(['duration_seconds' => (int) ceil($seconds)]);
        }
    }
}
