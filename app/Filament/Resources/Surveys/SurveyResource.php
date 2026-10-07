<?php

namespace App\Filament\Resources\Surveys;

use App\Filament\Concerns\HasTranslatedLabels;
use App\Filament\Resources\Surveys\Pages\CreateSurvey;
use App\Filament\Resources\Surveys\Pages\EditSurvey;
use App\Filament\Resources\Surveys\Pages\ListSurveys;
use App\Filament\Resources\Surveys\Pages\SurveyResultsPage;
use App\Filament\Resources\Surveys\Schemas\SurveyForm;
use App\Models\Survey;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * استبيانات قياس الرضا. الصلاحيات: surveys.* — SurveyPolicy. والحفظ عبر SaveSurvey، والنتائج المجمعة
 * في صفحتها، والحذف ناعم.
 */
class SurveyResource extends Resource
{
    use HasTranslatedLabels;

    protected static string $langKey = 'surveys';

    protected static string $navigationGroupKey = 'surveys';

    protected static ?string $model = Survey::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    public static function form(Schema $schema): Schema
    {
        return SurveyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('responses'))
            ->columns([
                TextColumn::make('title')->label(__('surveys.fields.title'))->searchable()->wrap(),
                TextColumn::make('audience')->label(__('surveys.fields.audience'))->badge(),
                IconColumn::make('is_published')->label(__('content.is_published'))->boolean(),
                TextColumn::make('responses_count')->label(__('surveys.fields.responses'))->numeric(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('results')
                    ->label(__('surveys.results.action'))
                    ->icon('heroicon-o-chart-pie')
                    ->url(fn (Survey $record): string => self::getUrl('results', ['record' => $record])),
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ]);
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
            'index' => ListSurveys::route('/'),
            'create' => CreateSurvey::route('/create'),
            'edit' => EditSurvey::route('/{record}/edit'),
            'results' => SurveyResultsPage::route('/{record}/results'),
        ];
    }
}
