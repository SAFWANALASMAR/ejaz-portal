<?php

namespace App\Filament\Resources\EvidenceGalleries\Pages;

use App\Actions\Evidence\IssueEvidenceLink;
use App\Actions\Evidence\RevokeEvidenceLink;
use App\Actions\Evidence\SaveEvidenceGallery;
use App\Filament\Concerns\SavesThroughAction;
use App\Filament\Resources\EvidenceGalleries\EvidenceGalleryResource;
use App\Models\EvidenceGallery;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * تعديل المعرض، ومنه إصدار الرابط السري أو تجديده (يظهر مرة واحدة في إشعار ثابت، ولا يُحفظ إلا بصمته)، وإلغاؤه فوراً.
 * وتحتها سجل الفتح بالوقت والشبكة المقنّعة.
 */
class EditEvidenceGallery extends EditRecord
{
    use SavesThroughAction;

    protected static string $resource = EvidenceGalleryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('issueLink')
                ->label(fn (EvidenceGallery $record): string => $record->token_hash === null ? __('evidence.actions.issue') : __('evidence.actions.regenerate'))
                ->icon(Heroicon::OutlinedLink)
                ->modalDescription(fn (EvidenceGallery $record): string => $record->token_hash === null ? __('evidence.actions.issue_description') : __('evidence.actions.regenerate_description'))
                ->schema([
                    DateTimePicker::make('expires_at')
                        ->label(__('evidence.fields.expires_at'))
                        ->helperText(__('evidence.fields.expires_at_limits', ['default' => EvidenceGallery::DEFAULT_LINK_DAYS, 'max' => EvidenceGallery::MAX_LINK_DAYS]))
                        ->seconds(false)
                        ->required()
                        ->default(fn () => now()->addDays(EvidenceGallery::DEFAULT_LINK_DAYS))
                        ->minDate(now())
                        ->maxDate(fn () => EvidenceGallery::latestExpiry()),
                ])
                ->authorize(fn (EvidenceGallery $record): bool => self::actor()->can('update', $record))
                ->visible(fn (EvidenceGallery $record): bool => ! $record->trashed())
                ->action(function (EvidenceGallery $record, array $data, IssueEvidenceLink $issue): void {
                    $url = $issue->handle(self::actor(), $record, $data['expires_at']);

                    Notification::make()->success()->persistent()
                        ->title(__('evidence.actions.issued'))
                        ->body($url)
                        ->send();
                }),
            Action::make('revokeLink')
                ->label(__('evidence.actions.revoke'))
                ->icon(Heroicon::OutlinedNoSymbol)
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription(__('evidence.actions.revoke_description'))
                ->authorize(fn (EvidenceGallery $record): bool => self::actor()->can('revoke', $record))
                ->visible(fn (EvidenceGallery $record): bool => $record->token_hash !== null && $record->revoked_at === null && ! $record->trashed())
                ->action(function (EvidenceGallery $record, RevokeEvidenceLink $revoke): void {
                    $revoke->handle(self::actor(), $record);

                    Notification::make()->success()->title(__('evidence.actions.revoked'))->send();
                }),
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var EvidenceGallery $record */
        $record = $this->getRecord();

        return EvidenceGalleryResource::fromRecord($record, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var EvidenceGallery $record */
        return $this->saveThroughAction($record, [], fn (): EvidenceGallery => app(SaveEvidenceGallery::class)->handle(self::actor(), $record, EvidenceGalleryResource::toAction($data)));
    }

    private static function actor(): User
    {
        /** @var User */
        return auth()->user();
    }
}
