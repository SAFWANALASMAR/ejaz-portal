<?php

namespace App\Actions\Library;

use App\Enums\LibrarySection;
use App\Models\LibraryProgress;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * «قراءاتي» لصاحب الحساب وحده (docs/decisions/2026-11-01-my-reading.md): تقدّمه في العناصر المنشورة، الأحدث أولاً، مقسوماً
 * إلى الكتب (والأبحاث) والصوتيات والمرئيات، ومجاميعه. بطاقة اللوحة تأخذ أحدثها، وصفحة «قراءاتي» كلها.
 */
class BuildMyReading
{
    /**
     * سقف ما يُعرض في الصفحة: عناصر المكتبة نفسها بالمئات، فلا ترقيم.
     */
    public const LIMIT = 200;

    /**
     * @return array{latest: Collection<int, LibraryProgress>, books: Collection<int, LibraryProgress>, audio: Collection<int, LibraryProgress>, videos: Collection<int, LibraryProgress>, totals: array{pages_read: int, listened_minutes: int, watched_minutes: int, readers: int}}
     */
    public function handle(User $user, int $limit = self::LIMIT): array
    {
        $progress = LibraryProgress::query()->visibleFor($user)->limit($limit)->get();

        return [
            'latest' => $progress,
            'books' => $progress->filter(fn (LibraryProgress $row): bool => $row->item->section->takesPdf())->values(),
            'audio' => $progress->filter(fn (LibraryProgress $row): bool => $row->item->section === LibrarySection::Audio)->values(),
            'videos' => $progress->filter(fn (LibraryProgress $row): bool => $row->item->section === LibrarySection::Videos)->values(),
            'totals' => LibraryProgress::totals($user),
        ];
    }
}
