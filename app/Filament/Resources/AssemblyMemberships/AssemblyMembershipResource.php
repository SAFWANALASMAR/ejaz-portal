<?php

namespace App\Filament\Resources\AssemblyMemberships;

use App\Enums\MembershipStatus;
use App\Filament\Concerns\HasTranslatedLabels;
use App\Filament\Resources\AssemblyMemberships\Pages\CreateAssemblyMembership;
use App\Filament\Resources\AssemblyMemberships\Pages\EditAssemblyMembership;
use App\Filament\Resources\AssemblyMemberships\Pages\ListAssemblyMemberships;
use App\Models\AssemblyMembership;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * سجل أعضاء الجمعية العمومية. الاطلاع للسكرتير والمجلس، والإنشاء والتعديل للمشرف العام وحده، لأنهما
 * يمنحان دور عضو الجمعية العمومية أو يسحبانه (SaveAssemblyMembership — AssemblyMembershipPolicy).
 */
class AssemblyMembershipResource extends Resource
{
    use HasTranslatedLabels;

    protected static string $langKey = 'assembly.memberships';

    protected static string $navigationGroupKey = 'assembly';

    protected static ?string $model = AssemblyMembership::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'serviceRequest']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->schema([
                // الحساب عند الإنشاء وحده: حساب فعّال لم تُخفَ بياناته وليست له عضوية
                Select::make('user_id')
                    ->label(__('assembly.fields.user'))
                    ->searchable()
                    ->required()
                    ->visibleOn('create')
                    ->getSearchResultsUsing(fn (string $search): array => User::query()
                        ->where('is_active', true)
                        ->whereNull('anonymized_at')
                        ->whereNotIn('id', AssemblyMembership::query()->select('user_id'))
                        ->where(fn (Builder $query) => $query->where('name', 'ilike', '%'.$search.'%')->orWhere('email', 'ilike', '%'.$search.'%'))
                        ->orderBy('name')
                        ->limit(20)
                        ->get()
                        ->mapWithKeys(fn (User $user): array => [$user->id => $user->name.' — '.$user->email])
                        ->all())
                    ->getOptionLabelUsing(fn (mixed $value): ?string => User::query()->find($value)?->name)
                    ->columnSpanFull(),
                Select::make('status')->label(__('assembly.fields.status'))->options(MembershipStatus::class)->default(MembershipStatus::Active->value)->required(),
                DatePicker::make('joined_on')->label(__('assembly.fields.joined_on')),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('user.name')->label(__('assembly.fields.user'))->searchable(),
                TextColumn::make('user.email')->label(__('users.fields.email'))->searchable()->toggleable(),
                TextColumn::make('joined_on')->label(__('assembly.fields.joined_on'))->date('Y-m-d')->placeholder('—')->sortable(),
                TextColumn::make('status')->label(__('assembly.fields.status'))->badge(),
                TextColumn::make('serviceRequest.reference')->label(__('assembly.fields.service_request'))->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('assembly.fields.status'))->options(MembershipStatus::class),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssemblyMemberships::route('/'),
            'create' => CreateAssemblyMembership::route('/create'),
            'edit' => EditAssemblyMembership::route('/{record}/edit'),
        ];
    }
}
