<?php

namespace App\Filament\Resources\Users;

use App\Enums\RoleName;
use App\Filament\Concerns\HasTranslatedLabels;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * المستخدمون وأدوارهم. بيانات شخصية: users.* للمشرف العام، وللمدير التنفيذي في حسابات الموظفين وحدها — UserPolicy.
 * الإنشاء والتعديل وإخفاء البيانات عبر Actions في App\Actions\Users.
 */
class UserResource extends Resource
{
    use HasTranslatedLabels;

    protected static string $langKey = 'users';

    protected static string $navigationGroupKey = 'users';

    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('roles');
        $actor = auth()->user();

        // المدير التنفيذي يرى حسابات الموظفين وحدها، كما في UserPolicy: لها دور إداري مما يمنحه،
        // ولا دور إدارياً غيره. فلا يرى المستفيدين ولا المشرف العام ولا المجلس ولا حسابه
        if ($actor instanceof User && ! $actor->hasRole(RoleName::SuperAdmin->value) && $actor->hasRole(RoleName::ExecutiveDirector->value)) {
            $panel = RoleName::values(RoleName::panelRoles());
            $managed = array_values(array_intersect($panel, RoleName::values(RoleName::assignableBy($actor))));

            $query->whereHas('roles', fn (Builder $roles) => $roles->whereIn('name', $managed))
                ->whereDoesntHave('roles', fn (Builder $roles) => $roles->whereIn('name', array_values(array_diff($panel, $managed))));
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
