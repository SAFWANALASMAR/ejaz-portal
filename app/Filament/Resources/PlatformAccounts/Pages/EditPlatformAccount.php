<?php

namespace App\Filament\Resources\PlatformAccounts\Pages;

use App\Actions\Activity\LogAccess;
use App\Actions\Registers\SavePlatformAccount;
use App\Enums\ActivityEvent;
use App\Filament\Concerns\SavesThroughAction;
use App\Filament\Resources\PlatformAccounts\PlatformAccountResource;
use App\Models\PlatformAccount;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPlatformAccount extends EditRecord
{
    use SavesThroughAction;

    protected static string $resource = PlatformAccountResource::class;

    /**
     * صفحة التعديل تعرض السجل كاملاً، فيُسجَّل فتحها اطلاعاً.
     */
    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var User $actor */
        $actor = auth()->user();
        app(LogAccess::class)->handle($actor, $this->getRecord(), 'registers', ActivityEvent::Viewed);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();
        /** @var PlatformAccount $record */

        return $this->saveThroughAction($record, [], fn (): PlatformAccount => app(SavePlatformAccount::class)->handle($actor, $record, PlatformAccountResource::toAction($data)));
    }
}
