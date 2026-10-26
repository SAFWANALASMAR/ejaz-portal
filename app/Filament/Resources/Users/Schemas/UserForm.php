<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\RoleName;
use App\Models\User;
use App\Rules\SaudiMobile;
use App\Support\SaudiPhone;
use Closure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->description(fn (string $operation) => $operation === 'create' ? __('users.create_hint') : null)
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('users.fields.name'))
                            ->required()
                            ->minLength(3)
                            ->maxLength(120)
                            ->columnSpanFull(),
                        // اسم المستخدم يختاره صاحب الحساب عند التسجيل، ويظهر هنا للاطلاع
                        TextInput::make('username')
                            ->label(__('users.fields.username'))
                            ->disabled()
                            ->dehydrated(false)
                            ->hiddenOn('create')
                            ->extraInputAttributes(['dir' => 'ltr'])
                            ->columnSpanFull(),
                        // البريد اختياري في حساب المستفيد (ملاحظات المدير التنفيذي، 25 أكتوبر 2026)، وإلزامي لحساب ينشئه
                        // المشرف لأن دعوة تعيين كلمة المرور تصل إليه
                        TextInput::make('email')
                            ->label(__('users.fields.email'))
                            ->email()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->maxLength(255)
                            ->extraInputAttributes(['dir' => 'ltr'])
                            ->rule(fn (?User $record) => self::uniqueRule('email', $record, fn (string $value) => mb_strtolower(trim($value)))),
                        TextInput::make('phone')
                            ->label(__('users.fields.phone'))
                            ->tel()
                            ->maxLength(20)
                            ->extraInputAttributes(['dir' => 'ltr'])
                            ->formatStateUsing(fn (?string $state) => $state ? SaudiPhone::display($state) : null)
                            ->rule(new SaudiMobile)
                            ->rule(fn (?User $record) => self::uniqueRule('phone', $record, fn (string $value) => SaudiPhone::normalize($value) ?? $value)),
                        Toggle::make('is_active')
                            ->label(__('users.fields.is_active'))
                            ->default(true)
                            ->hiddenOn('create')
                            // لا يوقف أحد حسابه هو
                            ->disabled(fn (?User $record) => $record !== null && $record->is(auth()->user())),
                        CheckboxList::make('roles')
                            ->label(__('users.fields.roles'))
                            ->helperText(__('users.fields.roles_hint'))
                            // ما يمنحه المنفّذ وحده (RoleName::assignableBy): مديرو النظام كلها عدا عضوية الجمعية
                            // العمومية (من سجل الأعضاء وحده)
                            ->options(fn () => collect(auth()->user() instanceof User ? RoleName::assignableBy(auth()->user()) : [])->mapWithKeys(fn (RoleName $role) => [$role->value => $role->getLabel()])->all())
                            ->columns(2)
                            ->columnSpanFull()
                            ->visible(fn () => auth()->user()?->can('assignRoles', User::class) ?? false)
                            // ChangeUserRoles يمنع ذلك أيضاً؛ وهنا رسالة واضحة بدل خطأ 403
                            ->rule(fn (?User $record) => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                $administrators = RoleName::values(RoleName::administrators());

                                if ($record?->is(auth()->user()) && $record->hasAnyRole($administrators) && array_intersect($administrators, (array) $value) === []) {
                                    $fail(__('users.cannot_demote_self'));
                                }
                            }),
                    ]),
            ]);
    }

    /**
     * قاعدة تفرّد تقارن القيمة بعد توحيدها كما تُخزَّن (البريد بأحرف صغيرة، والجوال دولياً).
     *
     * @param  Closure(string): string  $normalize
     */
    private static function uniqueRule(string $column, ?User $record, Closure $normalize): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($column, $record, $normalize): void {
            if (! is_string($value) || $value === '') {
                return;
            }

            $exists = User::query()
                ->where($column, $normalize($value))
                ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                // الجوال والبريد يتفردان على الموثق وحده (decisions.md — 14 أكتوبر 2026، و26 أكتوبر للبريد)، والبريد أيضاً على
                // الحساب القديم بلا اسم مستخدم، فهو معرّف دخوله الوحيد (User::emailTakenRule)
                ->where(fn ($query) => $query->whereNotNull($column.'_verified_at')
                    ->when($column === 'email', fn ($query) => $query->orWhereNull('username')))
                ->exists();

            if ($exists) {
                $fail(__('validation.unique', ['attribute' => __('users.fields.'.$column)]));
            }
        };
    }
}
