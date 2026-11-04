<?php

namespace App\Filament\Resources\Programs\Schemas;

use App\Enums\LibraryProgramKind;
use App\Enums\LibrarySection;
use App\Enums\ProgramDelivery;
use App\Filament\Forms\ContentFields;
use App\Filament\Forms\FormFieldsRepeater;
use App\Models\LibraryItem;
use App\Models\Program;
use App\Models\ProgramType;
use App\Rules\SafeUrl;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ProgramForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('programs.admin.sections.basics'))->icon(Heroicon::OutlinedAcademicCap)->columns(2)->columnSpanFull()->schema([
                TextInput::make('title')->label(__('programs.admin.fields.title'))->required()->maxLength(200)->columnSpanFull(),
                ContentFields::slug(),
                Select::make('program_type_id')->label(__('programs.admin.fields.type'))
                    ->options(fn (): array => ProgramType::query()->ordered()->pluck('name', 'id')->all())
                    ->required()
                    ->live(),
                // عنصر المكتبة لبرامج المكتبة وحدها، من قسم نوعها (SaveProgram يفحصه لكل مستدعٍ — docs/decisions/2026-11-02-reading-program.md)
                Select::make('library_item_id')->label(__('programs.library.item'))->helperText(__('programs.library.item_hint'))
                    ->visible(fn (Get $get): bool => self::kind($get('program_type_id')) !== null)
                    ->required(fn (Get $get): bool => self::kind($get('program_type_id')) !== null)
                    ->searchable()
                    ->options(fn (Get $get): array => ($kind = self::kind($get('program_type_id'))) === null ? [] : LibraryItem::query()->published()
                        ->whereIn('section', array_map(fn (LibrarySection $section): string => $section->value, $kind->sections()))
                        ->orderBy('title')->limit(500)->pluck('title', 'id')->all())
                    ->columnSpanFull(),
                Select::make('initiative_id')->label(__('programs.admin.fields.initiative'))->relationship('initiative', 'name'),
                Textarea::make('summary')->label(__('programs.admin.fields.summary'))->rows(2)->maxLength(500)->columnSpanFull(),
                Textarea::make('description')->label(__('programs.admin.fields.description'))->rows(8)->maxLength(10000)->columnSpanFull(),
                ContentFields::image('image', Program::IMAGE_DIRECTORY)
                    ->label(__('programs.admin.fields.image'))
                    ->helperText(__('content.image_hint'))
                    ->columnSpanFull(),
                Toggle::make('is_published')->label(__('content.is_published')),
            ]),
            Section::make(__('programs.admin.sections.schedule'))->icon(Heroicon::OutlinedCalendarDays)->columns(2)->columnSpanFull()->schema([
                // وبرنامج المكتبة يبقى مفتوحاً بعد بدئه حتى نهايته الاختيارية
                DateTimePicker::make('starts_at')->label(__('programs.admin.fields.starts_at'))->seconds(false)->required(),
                DateTimePicker::make('ends_at')->label(__('programs.admin.fields.ends_at'))->seconds(false)->afterOrEqual('starts_at'),
                Select::make('delivery')->label(__('programs.admin.fields.delivery'))->options(ProgramDelivery::class)->required(),
                TextInput::make('location')->label(__('programs.admin.fields.location'))->maxLength(255),
                TextInput::make('meeting_url')->label(__('programs.admin.fields.meeting_url'))->helperText(__('programs.admin.fields.meeting_url_hint'))
                    ->url()->maxLength(500)->rules([new SafeUrl(allowInternal: false)])->extraInputAttributes(['dir' => 'ltr'])->columnSpanFull(),
            ]),
            Section::make(__('programs.admin.sections.registration'))->icon(Heroicon::OutlinedClipboardDocumentCheck)->columns(2)->columnSpanFull()->schema([
                TextInput::make('seats')->label(__('programs.admin.fields.seats'))->helperText(__('programs.admin.fields.seats_hint'))->integer()->minValue(1)->maxValue(100000),
                Toggle::make('has_waitlist')->label(__('programs.admin.fields.has_waitlist'))->inline(false),
                DateTimePicker::make('registration_opens_at')->label(__('programs.admin.fields.registration_opens_at'))->helperText(__('programs.admin.fields.registration_opens_at_hint'))->seconds(false),
                DateTimePicker::make('registration_closes_at')->label(__('programs.admin.fields.registration_closes_at'))->helperText(__('programs.admin.fields.registration_closes_at_hint'))->seconds(false),
                TextInput::make('audience')->label(__('programs.admin.fields.audience'))->maxLength(255)->columnSpanFull(),
                Textarea::make('conditions')->label(__('programs.admin.fields.conditions'))->rows(4)->maxLength(5000)->columnSpanFull(),
            ]),
            Section::make(__('programs.admin.sections.form_fields'))
                ->icon(Heroicon::OutlinedQueueList)
                ->description(__('programs.admin.sections.form_fields_hint'))
                ->columnSpanFull()
                ->schema([FormFieldsRepeater::make(allowFiles: false)]),
        ]);
    }

    private static function kind(mixed $typeId): ?LibraryProgramKind
    {
        return blank($typeId) ? null : LibraryProgramKind::tryFrom((string) ProgramType::query()->whereKey($typeId)->value('slug'));
    }
}
