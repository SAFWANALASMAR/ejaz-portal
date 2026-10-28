<?php

namespace App\Filament\Resources\Users\Pages;

use App\Actions\Users\UpdateUserAccount;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Support\NationalIdExport;
use App\Support\SaudiPhone;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var User $user */
        $user = $this->getRecord();

        return [...$data, 'roles' => $user->getRoleNames()->all(), 'export_national_id' => $user->hasDirectPermission(NationalIdExport::PERMISSION)];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();
        /** @var User $record */
        try {
            app(UpdateUserAccount::class)->handle($actor, $record, [
                'name' => (string) $data['name'],
                'email' => filled($data['email'] ?? null) ? (string) $data['email'] : null,
                'phone' => filled($data['phone'] ?? null) ? SaudiPhone::normalize((string) $data['phone']) : null,
                'is_active' => (bool) ($data['is_active'] ?? $record->is_active),
            ], array_key_exists('roles', $data) ? array_values((array) $data['roles']) : null,
                // الخيار مخفي عمّن لا يغيّره، فلا يصل في بياناته
                array_key_exists('export_national_id', $data) ? (bool) $data['export_national_id'] : null);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(
                collect($exception->errors())->mapWithKeys(fn (array $messages, string $key) => ['data.'.$key => $messages])->all(),
            );
        }

        return $record->refresh();
    }
}
