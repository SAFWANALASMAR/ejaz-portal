<?php

namespace App\Filament\Resources\Documents;

use App\Models\Document;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * تنزيل ملف الوثيقة من اللوحة لمن يملك الاطلاع عليها (governance.view)، ولو لم تُنشر بعد.
 * بديل الرابط المؤقت الموقّع، الذي يبقى صالحاً لمن حصل عليه بعد إلغاء النشر.
 */
final class DownloadDocumentAction
{
    public static function make(): Action
    {
        return Action::make('downloadFile')
            ->label(__('governance.document.download'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->authorize(fn (Document $record): bool => auth()->user()?->can('view', $record) ?? false)
            // وثيقة الرابط المباشر بلا ملف تنزيله
            ->visible(fn (Document $record): bool => filled($record->file))
            ->action(fn (Document $record): StreamedResponse => Storage::disk(Document::disk())
                ->download((string) $record->file, $record->downloadName(), ['Content-Type' => 'application/pdf']));
    }
}
