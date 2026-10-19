<?php

namespace App\Filament\Resources\AssistantLogs;

use App\Enums\AssistantMode;
use App\Enums\AssistantRating;
use App\Filament\Concerns\HasTranslatedLabels;
use App\Filament\Resources\AssistantLogs\Pages\ListAssistantLogs;
use App\Filament\Resources\AssistantLogs\Pages\ViewAssistantLog;
use App\Models\AssistantLog;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * سجل محادثات المساعد للمراجعة (requirements/10): بلا عنوان IP ولا هوية، ويُحذف آلياً بعد مدة الاحتفاظ. لا إنشاء
 * ولا تعديل ولا حذف يدوي، بل تعليم الإجابة الخاطئة (FlagAssistantAnswer). الصلاحية ai_assistant.manage.
 */
class AssistantLogResource extends Resource
{
    use HasTranslatedLabels;

    protected static string $langKey = 'assistant.admin.logs';

    protected static string $navigationGroupKey = 'assistant';

    protected static ?string $model = AssistantLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static ?int $navigationSort = 40;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->description(__('assistant.admin.logs.privacy_hint'))
            ->columns([
                TextColumn::make('question')->label(__('assistant.admin.logs.question'))->searchable()->wrap()->limit(80),
                TextColumn::make('mode')->label(__('assistant.admin.logs.mode'))->badge(),
                TextColumn::make('rating')->label(__('assistant.admin.logs.rating'))->badge()->placeholder('—'),
                IconColumn::make('is_flagged')->label(__('assistant.admin.logs.is_flagged'))->boolean(),
                TextColumn::make('cost')->label(__('assistant.admin.logs.cost'))->numeric(decimalPlaces: 4),
                TextColumn::make('created_at')->label(__('assistant.admin.logs.created_at'))->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('mode')->label(__('assistant.admin.logs.mode'))->options(AssistantMode::class),
                SelectFilter::make('rating')->label(__('assistant.admin.logs.rating'))->options(AssistantRating::class),
                TernaryFilter::make('is_flagged')->label(__('assistant.admin.logs.is_flagged')),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        $multiline = ['style' => 'white-space: pre-line'];

        return $schema->components([
            Section::make()->columns(4)->columnSpanFull()->schema([
                TextEntry::make('created_at')->label(__('assistant.admin.logs.created_at'))->dateTime('Y-m-d H:i'),
                TextEntry::make('mode')->label(__('assistant.admin.logs.mode'))->badge(),
                TextEntry::make('rating')->label(__('assistant.admin.logs.rating'))->badge()->placeholder('—'),
                IconEntry::make('is_flagged')->label(__('assistant.admin.logs.is_flagged'))->boolean(),
                TextEntry::make('model')->label(__('assistant.admin.logs.model_name'))->placeholder('—'),
                TextEntry::make('tokens')->label(__('assistant.admin.logs.tokens'))
                    ->state(fn (AssistantLog $record): string => number_format($record->input_tokens).' / '.number_format($record->output_tokens)),
                TextEntry::make('cost')->label(__('assistant.admin.logs.cost'))->numeric(decimalPlaces: 6),
            ]),
            Section::make()->columnSpanFull()->schema([
                TextEntry::make('question')->label(__('assistant.admin.logs.question'))->extraAttributes($multiline),
                TextEntry::make('answer')->label(__('assistant.admin.logs.answer'))->placeholder('—')->extraAttributes($multiline),
                TextEntry::make('sources')->label(__('assistant.admin.logs.sources'))
                    ->state(fn (AssistantLog $record): array => array_map(fn (array $source): string => $source['title'].' — '.$source['url'], (array) $record->sources))
                    ->listWithLineBreaks()
                    ->placeholder('—'),
                TextEntry::make('flag_note')->label(__('assistant.admin.logs.flag_note'))->placeholder('—')->extraAttributes($multiline),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssistantLogs::route('/'),
            'view' => ViewAssistantLog::route('/{record}'),
        ];
    }
}
