<?php

namespace App\Filament\Resources\Users\Tables;

use App\Actions\Users\AnonymizeUser;
use App\Actions\Users\ResetPasswordByAdmin;
use App\Actions\Users\SendStaffInvitation;
use App\Enums\RoleName;
use App\Models\User;
use App\Support\Digits;
use App\Support\SaudiPhone;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class UsersTable
{
    /**
     * رقم الجوال المنسوخ بمسافات (+966 51 234 9876) يُبحث به كاملاً، وإلا صار كل جزء
     * منه كلمة مستقلة، ورمز الدولة وحده لا يطابق شيئاً. وبقية البحث بالكلمات كالمعتاد.
     */
    public static function isPhoneSearch(?string $search): bool
    {
        return preg_match('/^[\s+\-()]*\d[\d\s+\-()]*$/', Digits::latin((string) $search)) === 1;
    }

    /**
     * المشرف العام والمسؤول التقني: يطّلعان على كل الحسابات (RoleName::overseers).
     */
    private static function overseer(): bool
    {
        return auth()->user() instanceof User && RoleName::isOverseer(auth()->user());
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->splitSearchTerms(fn (ListRecords $livewire): bool => ! self::isPhoneSearch($livewire->getTableSearch()))
            ->columns([
                TextColumn::make('name')
                    ->label(__('users.fields.name'))
                    ->searchable(),
                TextColumn::make('username')
                    ->label(__('users.fields.username'))
                    ->searchable()
                    ->placeholder('—')
                    ->extraAttributes(['dir' => 'ltr']),
                TextColumn::make('email')
                    ->label(__('users.fields.email'))
                    ->searchable()
                    ->extraAttributes(['dir' => 'ltr']),
                TextColumn::make('phone')
                    ->label(__('users.fields.phone'))
                    ->formatStateUsing(fn (?string $state) => SaudiPhone::display($state))
                    // الرقم مخزن +9665XXXXXXXX: يُبحث بأرقامه بعد حذف الصفر الأول ورمز الدولة
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $digits = (string) preg_replace('/\D/', '', Digits::latin($search));
                        $digits = (string) preg_replace('/^(00966|966|0)/', '', $digits);

                        return $digits === '' ? $query : $query->where('phone', 'like', '%'.$digits.'%');
                    })
                    ->extraAttributes(['dir' => 'ltr']),
                TextColumn::make('roles.name')
                    ->label(__('users.fields.roles'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => RoleName::tryFrom($state)?->getLabel() ?? $state),
                IconColumn::make('is_active')
                    ->label(__('users.fields.is_active'))
                    ->boolean(),
                // يكشف أي الحسابات الإدارية بلا تحقق ثنائي: للمشرف العام والمسؤول التقني، ولو مُنحت users.view لغيرهما
                // (المدير التنفيذي في حسابات الموظفين)
                IconColumn::make('two_factor')
                    ->label(__('users.fields.two_factor'))
                    ->state(fn (User $record): bool => $record->two_factor_confirmed_at !== null)
                    ->boolean()
                    ->visible(fn (): bool => self::overseer())
                    ->toggleable(),
                TextColumn::make('last_login_at')
                    ->label(__('users.fields.last_login_at'))
                    ->since()
                    ->sortable(),
                TextColumn::make('deletion_requested_at')
                    ->label(__('users.fields.deletion_requested_at'))
                    ->dateTime('Y-m-d')
                    ->badge()
                    ->color('danger')
                    // طلبات الحذف يطّلع عليها المشرف العام ويعالجها المسؤول التقني
                    ->visible(fn (): bool => self::overseer())
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label(__('users.fields.created_at'))
                    ->dateTime('Y-m-d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->label(__('users.fields.roles'))
                    ->relationship('roles', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => RoleName::tryFrom($record->name)?->getLabel() ?? $record->name),
                TernaryFilter::make('is_active')
                    ->label(__('users.fields.is_active')),
                Filter::make('deletion_requested')
                    ->label(__('users.filters.deletion_requested'))
                    ->visible(fn (): bool => self::overseer())
                    ->query(fn (Builder $query) => $query->whereNotNull('deletion_requested_at')->whereNull('anonymized_at')),
            ])
            ->recordActions([
                EditAction::make(),
                // لمن لا بريد موثقاً له ولا جوالاً (ملاحظات المدير التنفيذي، 25 أكتوبر 2026): كلمة مؤقتة تُعرض مرة واحدة
                Action::make('resetPassword')
                    ->label(__('users.reset_password.action'))
                    ->icon(Heroicon::OutlinedKey)
                    ->requiresConfirmation()
                    ->modalHeading(__('users.reset_password.heading'))
                    ->modalDescription(__('users.reset_password.description'))
                    ->visible(fn (User $record) => auth()->user()?->can('resetPassword', $record) ?? false)
                    ->action(function (User $record, ResetPasswordByAdmin $reset): void {
                        /** @var User $actor */
                        $actor = auth()->user();

                        Notification::make()->success()->persistent()
                            ->title(__('users.reset_password.done'))
                            ->body(__('users.reset_password.password', ['password' => $reset->handle($actor, $record)]))
                            ->send();
                    }),
                Action::make('resendInvitation')
                    ->label(__('users.invitation.resend'))
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->requiresConfirmation()
                    ->modalDescription(__('users.invitation.resend_description'))
                    ->visible(fn (User $record) => auth()->user()?->can('invite', $record) ?? false)
                    ->action(function (User $record, SendStaffInvitation $invite): void {
                        /** @var User $actor */
                        $actor = auth()->user();

                        try {
                            $invite->handle($actor, $record);
                        } catch (ValidationException $exception) {
                            Notification::make()->danger()->title($exception->getMessage())->send();

                            return;
                        }

                        Notification::make()->success()->title(__('users.invitation.sent'))->send();
                    }),
                Action::make('anonymize')
                    ->label(__('users.anonymize.action'))
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('users.anonymize.heading'))
                    ->modalDescription(__('users.anonymize.description'))
                    ->visible(fn (User $record) => auth()->user()?->can('anonymize', $record) && AnonymizeUser::eligible(auth()->user(), $record))
                    ->action(function (User $record, AnonymizeUser $anonymize): void {
                        /** @var User $actor */
                        $actor = auth()->user();
                        $anonymize->handle($actor, $record);

                        Notification::make()->success()->title(__('users.anonymize.done'))->send();
                    }),
            ]);
    }
}
