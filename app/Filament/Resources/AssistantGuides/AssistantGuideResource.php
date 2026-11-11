<?php

namespace App\Filament\Resources\AssistantGuides;

use App\Enums\AssistantReviewStatus;
use App\Filament\Concerns\HasTranslatedLabels;
use App\Filament\Resources\AssistantGuides\Pages\CreateAssistantGuide;
use App\Filament\Resources\AssistantGuides\Pages\EditAssistantGuide;
use App\Filament\Resources\AssistantGuides\Pages\ListAssistantGuides;
use App\Models\AssistantGuide;
use App\Support\Assistant\GuideRoutes;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * «دليل استخدام البوابة» للمساعد: مقالات خطوات بصفحاتها. مسوداتها من الشيفرة (AssistantGuideSeeder)، ويفعّلها
 * المسؤول بعد مراجعتها. الصلاحية ai_assistant.manage — AssistantGuidePolicy، والفحص في SaveAssistantGuide.
 */
class AssistantGuideResource extends Resource
{
    use HasTranslatedLabels;

    protected static string $langKey = 'assistant.admin.guides';

    protected static string $navigationGroupKey = 'assistant';

    protected static ?string $model = AssistantGuide::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->schema([
                TextInput::make('title')->label(__('assistant.admin.guides.title'))->required()->maxLength(200),
                TextInput::make('slug')->label(__('assistant.admin.guides.slug'))->required()->maxLength(100)->extraInputAttributes(['dir' => 'ltr']),
                TextInput::make('keywords')->label(__('assistant.admin.guides.keywords'))->helperText(__('assistant.admin.guides.keywords_hint'))->maxLength(500)->columnSpanFull(),
                Select::make('route_name')->label(__('assistant.admin.guides.route_name'))->helperText(__('assistant.admin.guides.route_name_hint'))
                    ->options(fn (): array => GuideRoutes::options())
                    ->searchable()
                    ->required(),
                Select::make('status')->label(__('assistant.admin.guides.status'))->helperText(__('assistant.admin.guides.status_hint'))
                    ->options(AssistantReviewStatus::class)
                    ->default(AssistantReviewStatus::Draft->value)
                    ->required(),
                TextInput::make('sort')->label(__('assistant.admin.guides.sort'))->integer()->minValue(0)->default(0),
            ]),
            Section::make(__('assistant.admin.guides.steps'))->columnSpanFull()->schema([
                // التسمية مخفية، فزر الإضافة باسمه العربي لا باسم الحقل (فحص 11 نوفمبر 2026)
                Repeater::make('steps')
                    ->label(__('assistant.admin.guides.steps'))
                    ->hiddenLabel()
                    ->addActionLabel(__('assistant.admin.guides.add_step'))
                    ->simple(Textarea::make('step')->label(__('assistant.admin.guides.step'))->required()->rows(2)->maxLength(500))
                    ->reorderable()
                    ->minItems(1)
                    ->maxItems(12),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('title')->label(__('assistant.admin.guides.title'))->searchable()->wrap(),
                TextColumn::make('route_name')->label(__('assistant.admin.guides.route_name'))->extraAttributes(['dir' => 'ltr']),
                TextColumn::make('status')->label(__('assistant.admin.guides.status'))->badge(),
                TextColumn::make('sort')->label(__('assistant.admin.guides.sort'))->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('assistant.admin.guides.status'))->options(AssistantReviewStatus::class),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssistantGuides::route('/'),
            'create' => CreateAssistantGuide::route('/create'),
            'edit' => EditAssistantGuide::route('/{record}/edit'),
        ];
    }
}
