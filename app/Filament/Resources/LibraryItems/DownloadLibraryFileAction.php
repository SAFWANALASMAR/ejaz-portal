<?php

namespace App\Filament\Resources\LibraryItems;

use App\Models\LibraryItem;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * تنزيل ملف العنصر من اللوحة لمن يملك الاطلاع عليه (library.view)، ولو لم يُنشر بعد.
 * بديل الرابط المؤقت الموقّع، الذي يبقى صالحاً لمن حصل عليه بعد إلغاء النشر.
 */
final class DownloadLibraryFileAction
{
    public static function make(): Action
    {
        return Action::make('downloadFile')
            ->label(__('library.item.download'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->visible(fn (LibraryItem $record): bool => filled($record->file))
            ->authorize(fn (LibraryItem $record): bool => auth()->user()?->can('view', $record) ?? false)
            ->action(fn (LibraryItem $record): StreamedResponse => Storage::disk(LibraryItem::disk())
                ->download((string) $record->file, $record->downloadName(), ['Content-Type' => $record->fileContentType()]));
    }
}
