<?php

namespace App\Filament\Resources\Meetings\Schemas;

use App\Enums\MeetingType;
use App\Models\Meeting;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * نموذج الاجتماع للسكرتير. والمرفقات PDF في القرص الخاص بأسماء عشوائية وأسمائها الأصلية، ويفحصها
 * SaveMeeting (PDF حقيقي في مجلدها) ويحذف المخالف.
 */
class MeetingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->schema([
                TextInput::make('title')->label(__('assembly.fields.title'))->required()->maxLength(200)->columnSpanFull(),
                Select::make('type')->label(__('assembly.fields.type'))->options(MeetingType::class)->required(),
                TextInput::make('quorum_percent')
                    ->label(__('assembly.fields.quorum_percent'))
                    ->helperText(__('assembly.fields.quorum_percent_hint'))
                    ->integer()
                    ->minValue(1)
                    ->maxValue(100),
                DateTimePicker::make('starts_at')->label(__('assembly.fields.starts_at'))->seconds(false)->required(),
                DateTimePicker::make('ends_at')->label(__('assembly.fields.ends_at'))->helperText(__('assembly.fields.ends_at_hint'))->seconds(false)->after('starts_at'),
                Toggle::make('is_online')->label(__('assembly.fields.is_online'))->live()->columnSpanFull(),
                TextInput::make('location')
                    ->label(__('assembly.fields.location'))
                    ->maxLength(300)
                    ->required(fn (Get $get): bool => ! $get('is_online'))
                    ->columnSpanFull(),
                Textarea::make('agenda')->label(__('assembly.fields.agenda'))->rows(6)->maxLength(5000)->columnSpanFull(),
                FileUpload::make('attachments')
                    ->label(__('assembly.fields.attachments'))
                    ->multiple()
                    ->maxFiles(10)
                    ->disk(Meeting::disk())
                    ->directory(Meeting::ATTACHMENTS_DIRECTORY)
                    ->visibility('private')
                    ->acceptedFileTypes(['application/pdf'])
                    ->maxSize(20480)
                    ->storeFileNamesIn('attachment_names')
                    // المحفوظة مساراتٌ داخل [path, name]، فيُجاز منها ما يخص هذا الاجتماع وحده
                    ->preventFilePathTampering(allowFilePathUsing: fn (string $file, ?Meeting $record): bool => $record !== null && in_array($file, array_column($record->attachments ?? [], 'path'), true))
                    // لا رابط مؤقت موقّع: المرفق يُنزَّل من صفحة الاجتماع بإجراء يفحص الصلاحية
                    ->previewable(false)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    /**
     * بيانات النموذج كما يأخذها SaveMeeting: المرفقات مساراتٍ بأسمائها الأصلية.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function toAction(array $data): array
    {
        /** @var array<string, string> $names */
        $names = (array) ($data['attachment_names'] ?? []);
        $paths = array_values(array_filter((array) ($data['attachments'] ?? []), 'is_string'));

        unset($data['attachment_names']);
        $data['attachments'] = array_map(fn (string $path): array => ['path' => $path, 'name' => $names[$path] ?? basename($path)], $paths);

        return $data;
    }

    /**
     * والعكس عند فتح النموذج: المرفقات المحفوظة مساراتٍ وأسماءً.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fromRecord(Meeting $meeting, array $data): array
    {
        $attachments = $meeting->attachments ?? [];

        $data['attachments'] = array_column($attachments, 'path');
        $data['attachment_names'] = array_column($attachments, 'name', 'path');

        return $data;
    }
}
