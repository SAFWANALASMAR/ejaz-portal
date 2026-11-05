<?php

namespace App\Filament\Resources\Users\Pages;

use App\Actions\Users\ReleasePhone;
use App\Actions\Users\ResetTwoFactorByAdmin;
use App\Actions\Users\UpdateUserAccount;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Support\NationalIdExport;
use App\Support\SaudiPhone;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // رقم غير موثق حجزه حساب عن صاحبه (decisions/2026-11-05-phone-login.md): لمديري النظام، بسجل وتنبيه (ReleasePhone)
            Action::make('releasePhone')
                ->label(__('users.release_phone.action'))
                ->icon(Heroicon::OutlinedDevicePhoneMobile)
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(__('users.release_phone.heading'))
                // وتحذير صريح حين يكون الجوال معرّف دخوله الوحيد: بلا اسم مستخدم ولا بريد يدخل به (مراجعة المشرف، L4)
                ->modalDescription(fn (User $record): string => __('users.release_phone.description')
                    .($record->username === null && $record->email === null ? ' '.__('users.release_phone.only_identifier') : ''))
                ->visible(fn (User $record): bool => auth()->user()?->can('releasePhone', $record) ?? false)
                ->action(function (User $record, ReleasePhone $release): void {
                    /** @var User $actor */
                    $actor = auth()->user();
                    $release->handle($actor, $record);
                    $record->refresh();
                    $this->refreshFormData(['phone']);

                    Notification::make()->success()->title(__('users.release_phone.done'))->send();
                }),
            // لمن فقد تطبيق المصادقة (docs/decisions/2026-11-03-2fa-admin-reset.md): بكلمة مرور المنفّذ وسبب مكتوب.
            // وخطأ الإجراء (كلمة مرور خاطئة أو تجاوز الحد) تنبيه تُغلق معه النافذة. وخطأ النموذج نفسه (سبب فارغ أو طويل)
            // يُبقيها مفتوحة بكلمة المرور في حالتها حتى تُغلق، في متصفح المنفّذ نفسه
            Action::make('resetTwoFactor')
                ->label(__('users.reset_two_factor.action'))
                ->icon(Heroicon::OutlinedShieldExclamation)
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(__('users.reset_two_factor.heading'))
                ->modalDescription(__('users.reset_two_factor.description'))
                ->visible(fn (User $record): bool => auth()->user()?->can('resetTwoFactor', $record) ?? false)
                ->schema([
                    TextInput::make('password')->label(__('users.reset_two_factor.password'))
                        ->password()->revealable()->required()->autocomplete('current-password'),
                    Textarea::make('reason')->label(__('users.reset_two_factor.reason'))
                        ->helperText(__('users.reset_two_factor.reason_hint'))
                        ->required()->maxLength(ResetTwoFactorByAdmin::REASON_MAX),
                ])
                ->action(function (User $record, array $data, ResetTwoFactorByAdmin $reset): void {
                    /** @var User $actor */
                    $actor = auth()->user();

                    try {
                        $reset->handle($actor, $record, $data);
                    } catch (ValidationException $exception) {
                        Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();

                        return;
                    }

                    Notification::make()->success()->title(__('users.reset_two_factor.done_link'))->send();
                }),
        ];
    }

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
