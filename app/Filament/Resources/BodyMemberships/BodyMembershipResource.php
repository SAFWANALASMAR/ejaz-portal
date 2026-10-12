<?php

namespace App\Filament\Resources\BodyMemberships;

use App\Enums\BodyRole;
use App\Enums\MeetingBody;
use App\Filament\Concerns\HasTranslatedLabels;
use App\Filament\Resources\BodyMemberships\Pages\CreateBodyMembership;
use App\Filament\Resources\BodyMemberships\Pages\EditBodyMembership;
use App\Filament\Resources\BodyMemberships\Pages\ListBodyMemberships;
use App\Models\BodyMembership;
use App\Models\Committee;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * أعضاء مجلس الإدارة واللجان بصفاتهم ومدة عضويتهم (requirements/13 §2). يديرها السكرتير، ويطّلع عليها
 * المدير التنفيذي والمجلس (committees.* — BodyMembershipPolicy).
 */
class BodyMembershipResource extends Resource
{
    use HasTranslatedLabels;

    protected static string $langKey = 'registers.membership';

    protected static string $navigationGroupKey = 'assembly';

    protected static ?string $model = BodyMembership::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?int $navigationSort = 6;

    /**
     * @return Builder<Model>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'committee']);
    }

    public static function form(Schema $schema): Schema
    {
        $isCommittee = fn (Get $get): bool => self::bodyValue($get('body')) === MeetingBody::Committee->value;

        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->schema([
                Select::make('body')
                    ->label(__('registers.fields.body'))
                    ->options(collect(MeetingBody::membershipBodies())->mapWithKeys(fn (MeetingBody $body): array => [$body->value => $body->getLabel()])->all())
                    ->required()
                    ->live(),
                Select::make('committee_id')
                    ->label(__('registers.fields.committee'))
                    ->options(fn (): array => Committee::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->visible($isCommittee)
                    ->required($isCommittee),
                Select::make('user_id')
                    ->label(__('registers.fields.user'))
                    // بحث في الخادم بالاسم أو البريد: الحسابات كثيرة فلا تُحمَّل كلها
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => User::query()->where('is_active', true)->whereNull('anonymized_at')
                        ->where(fn ($query) => $query->whereLike('name', '%'.$search.'%')->orWhereLike('email', '%'.$search.'%'))
                        ->orderBy('name')->limit(50)->pluck('name', 'id')->all())
                    ->getOptionLabelUsing(fn ($value): ?string => User::query()->whereKey($value)->value('name'))
                    ->required(),
                Select::make('role')->label(__('registers.fields.role'))->options(BodyRole::class)->default(BodyRole::Member->value)->required(),
                DatePicker::make('starts_on')->label(__('registers.fields.starts_on'))->required(),
                DatePicker::make('ends_on')->label(__('registers.fields.ends_on'))->afterOrEqual('starts_on'),
                Textarea::make('notes')->label(__('registers.fields.notes'))->rows(2)->maxLength(2000)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_on', 'desc')
            ->columns([
                TextColumn::make('user.name')->label(__('registers.fields.user'))->searchable(),
                TextColumn::make('body')->label(__('registers.fields.body'))->badge(),
                TextColumn::make('committee.name')->label(__('registers.fields.committee'))->placeholder('—'),
                TextColumn::make('role')->label(__('registers.fields.role'))->badge(),
                TextColumn::make('starts_on')->label(__('registers.fields.starts_on'))->date('Y-m-d')->sortable(),
                TextColumn::make('ends_on')->label(__('registers.fields.ends_on'))->date('Y-m-d')->placeholder('—'),
                IconColumn::make('active')->label(__('registers.fields.active'))->boolean()->state(fn (BodyMembership $record): bool => $record->isActive()),
            ])
            ->filters([
                SelectFilter::make('body')->label(__('registers.fields.body'))->options(collect(MeetingBody::membershipBodies())->mapWithKeys(fn (MeetingBody $body): array => [$body->value => $body->getLabel()])->all()),
                SelectFilter::make('committee_id')->label(__('registers.fields.committee'))->relationship('committee', 'name'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ]);
    }

    private static function bodyValue(mixed $body): ?string
    {
        return $body instanceof MeetingBody ? $body->value : (is_string($body) ? $body : null);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function toAction(array $data): array
    {
        $data['body'] = self::bodyValue($data['body'] ?? null);
        $data['role'] = $data['role'] instanceof BodyRole ? $data['role']->value : ($data['role'] ?? null);
        // اللجنة للجنة وحدها: الحقل المخفي قد يبقى بقيمته
        $data['committee_id'] = $data['body'] === MeetingBody::Committee->value ? ($data['committee_id'] ?? null) : null;

        return $data;
    }

    /**
     * @return Builder<Model>
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBodyMemberships::route('/'),
            'create' => CreateBodyMembership::route('/create'),
            'edit' => EditBodyMembership::route('/{record}/edit'),
        ];
    }
}
