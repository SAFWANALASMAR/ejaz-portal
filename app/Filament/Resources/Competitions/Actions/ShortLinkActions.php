<?php

namespace App\Filament\Resources\Competitions\Actions;

use App\Models\Competition;
use App\Support\CompetitionShortLink;
use Filament\Actions\Action;
use Illuminate\Support\HtmlString;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * الرابط المختصر لمسابقة الطلاب ورمز QR له (مطوية «البراهين العلمية»: الرابط يُعمَّم على المدارس عبر إدارة النشاط
 * الطلابي): عرضهما، وتنزيل الرمز SVG للطباعة. لمن يطّلع على المسابقة، والرابط عام أصلاً.
 */
final class ShortLinkActions
{
    /**
     * @param  callable(): Competition  $competition
     * @return list<Action>
     */
    public static function make(callable $competition): array
    {
        $url = fn (): ?string => CompetitionShortLink::url($competition());

        return [
            Action::make('short_link')
                ->label(__('competitions.student.admin.short_link'))
                ->icon('heroicon-o-qr-code')
                ->color('gray')
                ->visible(fn (): bool => $url() !== null)
                ->modalHeading(__('competitions.student.admin.short_link'))
                ->modalDescription(__('competitions.student.admin.short_link_hint'))
                ->modalContent(fn (): HtmlString => new HtmlString(
                    '<div class="space-y-4 text-center"><p dir="ltr" class="font-mono text-lg font-semibold select-all">'.e((string) $url()).'</p>'
                    .'<div class="mx-auto w-64" role="img" aria-label="'.e(__('competitions.student.admin.short_link')).'">'.CompetitionShortLink::qrSvg((string) $url()).'</div></div>',
                ))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('competitions.student.admin.close')),
            Action::make('download_qr')
                ->label(__('competitions.student.admin.download_qr'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn (): bool => $url() !== null)
                ->action(fn (): StreamedResponse => response()->streamDownload(
                    function () use ($url): void {
                        echo '<?xml version="1.0" encoding="UTF-8"?>'."\n".CompetitionShortLink::qrSvg((string) $url());
                    },
                    'qr-'.$competition()->short_code.'.svg',
                    ['Content-Type' => 'image/svg+xml'],
                )),
        ];
    }
}
