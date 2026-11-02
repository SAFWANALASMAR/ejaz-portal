<?php

namespace App\Actions\Library;

use App\Models\LibraryProgress;
use App\Support\PageSet;

/**
 * يقصّ تقدّم القرّاء في كتاب إلى عدد صفحاته الحقيقي حين يُعرف العدد بعد حفظ صفحات أبعد منه (قبل العد، أو كتاب استُبدل ملفه
 * أو استُعيد بتقدّمه القديم): لا صفحة فوق العدد في المجموعة ولا في عددها ولا في الإتمام (مراجعة المشرف الأمنية على #117، م٢
 * ون٤). ويستدعيه CountLibraryPages بعد العد، ويقصّ RecordLibraryProgress بالقاعدة نفسها عند كل بلاغ (PageSet::clip).
 */
class ClipLibraryProgress
{
    public function handle(int $itemId, int $total): void
    {
        LibraryProgress::query()->where('library_item_id', $itemId)->whereNotNull('pages_seen')->lazyById()
            ->each(function (LibraryProgress $progress) use ($total): void {
                $pages = PageSet::clip($progress->pages(), $total);

                $progress->forceFill([
                    'pages_seen' => PageSet::encode($pages),
                    'pages_count' => count($pages),
                    'total_pages' => $total,
                    'last_page' => $progress->last_page === null ? null : min($progress->last_page, $total),
                    'completed_at' => count($pages) >= $total ? ($progress->completed_at ?? now()) : null,
                ]);

                if ($progress->isDirty()) {
                    $progress->save();
                }
            });
    }
}
