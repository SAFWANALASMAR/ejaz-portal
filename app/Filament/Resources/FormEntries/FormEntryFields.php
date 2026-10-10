<?php

namespace App\Filament\Resources\FormEntries;

use App\Enums\DocumentFieldType;
use App\Filament\Forms\SignaturePad;
use App\Models\FormEntry;
use App\Models\FormTemplateVersion;
use App\Rules\RealPdf;
use App\Support\DocumentFields;
use App\Support\SignatureImage;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

/**
 * حقول المعبأ في اللوحة من إصدار النموذج: الإجابات في answers، والمرفقات في files (بأسمائها في
 * file_names)، والتواقيع في signatures. وتحويلها من المعبأ وإليه كما يأخذها SaveFormEntry.
 */
final class FormEntryFields
{
    /**
     * @return list<Field>
     */
    public static function schema(FormTemplateVersion $version, ?FormEntry $entry = null): array
    {
        return array_map(fn (array $field): Field => self::component($field, $entry), $version->typedFields());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{answers: array<string, mixed>, files: array<string, array{path: string, name: string}>, signatures: array<string, mixed>}
     */
    public static function toAction(array $data): array
    {
        $names = (array) ($data['file_names'] ?? []);
        $files = [];

        foreach ((array) ($data['files'] ?? []) as $key => $path) {
            if (is_string($path) && $path !== '') {
                $files[(string) $key] = ['path' => $path, 'name' => (string) ($names[$key] ?? basename($path))];
            }
        }

        return [
            'answers' => (array) ($data['answers'] ?? []),
            'files' => $files,
            'signatures' => array_filter((array) ($data['signatures'] ?? []), fn (mixed $value): bool => filled($value)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromEntry(FormEntry $entry): array
    {
        $files = [];
        $names = [];

        foreach ($entry->files ?? [] as $file) {
            $files[$file['key']] = $file['path'];
            $names[$file['key']] = $file['name'];
        }

        $signatures = [];

        foreach ($entry->signatures ?? [] as $signature) {
            $signatures[$signature['key']] = SignatureImage::dataUrl(FormEntry::disk(), $signature['path']);
        }

        return ['answers' => $entry->answers ?? [], 'files' => $files, 'file_names' => $names, 'signatures' => $signatures];
    }

    /**
     * @param  array{key: string, label: string, type: DocumentFieldType, required: bool, options: list<string>}  $field
     */
    private static function component(array $field, ?FormEntry $entry): Field
    {
        $key = $field['key'];

        $component = match ($field['type']) {
            DocumentFieldType::Text => TextInput::make('answers.'.$key)->maxLength(255),
            DocumentFieldType::LongText => Textarea::make('answers.'.$key)->rows(4)->maxLength(5000),
            DocumentFieldType::Number => TextInput::make('answers.'.$key)->numeric(),
            DocumentFieldType::Date => DatePicker::make('answers.'.$key),
            DocumentFieldType::Select => Select::make('answers.'.$key)->options(array_combine($field['options'], $field['options'])),
            DocumentFieldType::File => FileUpload::make('files.'.$key)
                ->disk(FormEntry::disk())
                ->directory(FormEntry::FILES_DIRECTORY)
                ->visibility('private')
                ->acceptedFileTypes(['application/pdf'])
                ->maxSize(DocumentFields::FILE_MAX_KB)
                ->rules([new RealPdf])
                ->storeFileNamesIn('file_names.'.$key)
                ->previewable(false)
                // المحفوظة مساراتٌ داخل [key, path]، فيُجاز منها ما يخص هذا المعبأ وحده
                ->preventFilePathTampering(allowFilePathUsing: fn (string $file): bool => $entry !== null && in_array($file, array_column($entry->files ?? [], 'path'), true)),
            DocumentFieldType::Signature => SignaturePad::make('signatures.'.$key),
        };

        return $component->label($field['label'])->required($field['required'])->columnSpanFull();
    }
}
