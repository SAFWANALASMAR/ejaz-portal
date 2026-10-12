<?php

namespace App\Filament\Resources\AnnualPlanItems;

use App\Filament\Concerns\HasTranslatedLabels;
use App\Filament\Resources\AnnualPlanItems\Pages\CreateAnnualPlanItem;
use App\Filament\Resources\AnnualPlanItems\Pages\EditAnnualPlanItem;
use App\Filament\Resources\AnnualPlanItems\Pages\ListAnnualPlanItems;
use App\Models\AnnualPlanItem;
use App\Models\Program;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
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
 * الخطة السنوية للبرامج (requirements/13 §3). يديرها المدير التنفيذي ومدير المشاريع، ويطّلع عليها المجلس
 * (annual_plan.* — AnnualPlanItemPolicy). المنفَّذ والمستفيدون الفعليون محسوبان من البرنامج المربوط.
 */
class AnnualPlanItemResource extends Resource
{
    use HasTranslatedLabels;

    protected static string $langKey = 'registers.plan';

    protected static string $navigationGroupKey = 'registers';

    protected static ?string $model = AnnualPlanItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDateRange;

    protected static ?int $navigationSort = 2;

    /**
     * @return Builder<Model>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->scopes(['withOutcome']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->columnSpanFull()->schema([
                TextInput::make('year')->label(__('registers.fields.year'))->integer()->minValue(2000)->maxValue(2100)->required()->default(fn (): int => (int) now()->year),
                TextInput::make('department')->label(__('registers.fields.department'))->maxLength(200),
                TextInput::make('type')->label(__('registers.fields.plan_type'))->maxLength(200),
                TextInput::make('title')->label(__('registers.fields.activity'))->required()->maxLength(300)->columnSpanFull(),
                Textarea::make('objective_breakdown')->label(__('registers.fields.objective_breakdown'))->rows(2)->maxLength(5000)->columnSpanFull(),
                TextInput::make('estimated_budget')->label(__('registers.fields.estimated_budget'))->numeric()->minValue(0),
                TextInput::make('target_group')->label(__('registers.fields.target_group'))->maxLength(300),
                TextInput::make('target_beneficiaries')->label(__('registers.fields.target_beneficiaries'))->integer()->minValue(0),
                Textarea::make('supporting_entities')->label(__('registers.fields.supporting_entities'))->rows(2)->maxLength(2000)->columnSpanFull(),
                TextInput::make('preparation_period')->label(__('registers.fields.preparation_period'))->maxLength(200),
                DatePicker::make('starts_on')->label(__('registers.fields.plan_starts_on')),
                DatePicker::make('ends_on')->label(__('registers.fields.plan_ends_on'))->afterOrEqual('starts_on'),
                Textarea::make('approvals')->label(__('registers.fields.approvals'))->rows(2)->maxLength(2000)->columnSpanFull(),
                Select::make('program_id')
                    ->label(__('registers.fields.program'))
                    ->options(fn (): array => Program::query()->orderByDesc('starts_at')->limit(300)->pluck('title', 'id')->all())
                    ->searchable()
                    ->columnSpanFull(),
                Textarea::make('notes')->label(__('registers.fields.notes'))->rows(2)->maxLength(5000)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_on')
            ->columns([
                TextColumn::make('year')->label(__('registers.fields.year'))->sortable(),
                TextColumn::make('department')->label(__('registers.fields.department'))->searchable()->placeholder('—'),
                TextColumn::make('type')->label(__('registers.fields.plan_type'))->placeholder('—'),
                TextColumn::make('title')->label(__('registers.fields.activity'))->searchable()->wrap(),
                TextColumn::make('starts_on')->label(__('registers.fields.plan_starts_on'))->date('Y-m-d')->sortable()->placeholder('—'),
                TextColumn::make('target_beneficiaries')->label(__('registers.fields.target_beneficiaries'))->numeric()->placeholder('—'),
                TextColumn::make('program.title')->label(__('registers.fields.program'))->placeholder('—')->toggleable(),
                IconColumn::make('executed')->label(__('registers.fields.executed'))->boolean()->state(fn (AnnualPlanItem $record): bool => $record->isExecuted()),
                TextColumn::make('actual')->label(__('registers.fields.actual_beneficiaries'))->state(fn (AnnualPlanItem $record): string => number_format($record->actualBeneficiaries())),
            ])
            ->filters([
                SelectFilter::make('year')->label(__('registers.fields.year'))->options(fn (): array => AnnualPlanItem::query()->distinct()->orderByDesc('year')->pluck('year', 'year')->all()),
                SelectFilter::make('department')->label(__('registers.fields.department'))->options(fn (): array => AnnualPlanItem::query()->whereNotNull('department')->distinct()->orderBy('department')->pluck('department', 'department')->all()),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function toAction(array $data): array
    {
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
            'index' => ListAnnualPlanItems::route('/'),
            'create' => CreateAnnualPlanItem::route('/create'),
            'edit' => EditAnnualPlanItem::route('/{record}/edit'),
        ];
    }
}
