<?php

namespace App\Filament\Resources\AssistantFaqs;

use App\Enums\AssistantReviewStatus;
use App\Filament\Concerns\HasTranslatedLabels;
use App\Filament\Resources\AssistantFaqs\Pages\CreateAssistantFaq;
use App\Filament\Resources\AssistantFaqs\Pages\EditAssistantFaq;
use App\Filament\Resources\AssistantFaqs\Pages\ListAssistantFaqs;
use App\Models\AssistantFaq;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
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
 * الأسئلة الشائعة المعتمدة للمساعد (requirements/10). الصلاحية ai_assistant.manage — AssistantFaqPolicy.
 * والفحص كله في SaveAssistantFaq.
 */
class AssistantFaqResource extends Resource
{
    use HasTranslatedLabels;

    protected static string $langKey = 'assistant.admin.faqs';

    protected static string $navigationGroupKey = 'assistant';

    protected static ?string $model = AssistantFaq::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'question';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->schema([
                TextInput::make('question')->label(__('assistant.admin.faqs.question'))->required()->maxLength(300)->columnSpanFull(),
                Textarea::make('answer')->label(__('assistant.admin.faqs.answer'))->helperText(__('assistant.admin.faqs.answer_hint'))->required()->rows(5)->maxLength(3000)->columnSpanFull(),
                TextInput::make('keywords')->label(__('assistant.admin.faqs.keywords'))->helperText(__('assistant.admin.faqs.keywords_hint'))->maxLength(500)->columnSpanFull(),
                TextInput::make('source_url')->label(__('assistant.admin.faqs.source_url'))->helperText(__('assistant.admin.faqs.source_url_hint'))->required()->maxLength(500)->extraInputAttributes(['dir' => 'ltr']),
                Select::make('status')->label(__('assistant.admin.faqs.status'))->helperText(__('assistant.admin.faqs.status_hint'))
                    ->options([
                        AssistantReviewStatus::Draft->value => AssistantReviewStatus::Draft->getLabel(),
                        AssistantReviewStatus::Approved->value => AssistantReviewStatus::Approved->getLabel(),
                    ])
                    ->default(AssistantReviewStatus::Draft->value)
                    ->required(),
                TextInput::make('sort')->label(__('assistant.admin.faqs.sort'))->integer()->minValue(0)->default(0),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('question')->label(__('assistant.admin.faqs.question'))->searchable()->wrap()->limit(80),
                TextColumn::make('source_url')->label(__('assistant.admin.faqs.source_url'))->extraAttributes(['dir' => 'ltr']),
                TextColumn::make('status')->label(__('assistant.admin.faqs.status'))->badge(),
                TextColumn::make('sort')->label(__('assistant.admin.faqs.sort'))->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('assistant.admin.faqs.status'))->options(AssistantReviewStatus::class),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssistantFaqs::route('/'),
            'create' => CreateAssistantFaq::route('/create'),
            'edit' => EditAssistantFaq::route('/{record}/edit'),
        ];
    }
}
