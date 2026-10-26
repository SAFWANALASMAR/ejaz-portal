<?php

namespace App\Filament\Resources\FormEntries;

use App\Enums\DocumentFieldType;
use App\Enums\FormAudience;
use App\Filament\Forms\SignaturePad;
use App\Models\FormEntry;
use App\Models\FormTemplateVersion;
use App\Models\User;
use App\Rules\RealPdf;
use App\Support\DocumentFields;
use App\Support\SignatureImage;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Text;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;

/**
 * حقول المعبأ في اللوحة من إصدار النموذج، بترتيبه وشكله الورقي: عناوين الأقسام والنصوص الثابتة، وحقلا
 * نصف العرض صف من عمودين، والجدول صفوف يضيفها المعبّئ. الإجابات في answers، والمرفقات في files (بأسمائها
 * في file_names)، والتواقيع في signatures، وصاحب النموذج في person_id. وتحويلها من المعبأ وإليه كما
 * يأخذها SaveFormEntry.
 */
final class FormEntryFields
{
    /**
     * @return list<Component>
     */
    public static function schema(FormTemplateVersion $version, ?FormEntry $entry = null): array
    {
        return [
            self::personSelect($version->template->audience),
            Grid::make(['default' => 1, 'md' => 2])
                ->schema(array_map(fn (array $field): Component => self::component($field, $entry), $version->typedFields()))
                ->columnSpanFull(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{person_id: ?int, answers: array<string, mixed>, files: array<string, array{path: string, name: string}>, signatures: array<string, mixed>}
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
            'person_id' => filled($data['person_id'] ?? null) ? (int) $data['person_id'] : null,
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

        return ['person_id' => $entry->person_id, 'answers' => $entry->answers ?? [], 'files' => $files, 'file_names' => $names, 'signatures' => $signatures];
    }

    /**
     * صاحب النموذج من جمهوره، بالبحث بالاسم (لا قائمة بكل الحسابات)، ويتحقق منه SaveFormEntry.
     */
    private static function personSelect(FormAudience $audience): Select
    {
        return Select::make('person_id')
            ->label(__('documents.fields.person'))
            ->helperText(__('documents.fields.person_hint'))
            ->searchable()
            // ثلاثة أحرف على الأقل، فلا تُسرد أسماء الحسابات لمن لا يطّلع على المستخدمين
            ->searchPrompt(__('documents.fields.person_search_min'))
            ->getSearchResultsUsing(fn (string $search): array => mb_strlen(trim($search)) < 3 ? [] : $audience->people()
                ->where('name', 'ilike', '%'.addcslashes(trim($search), '%_\\').'%')
                ->orderBy('name')
                ->limit(20)
                ->pluck('name', 'id')
                ->all())
            // اسم من جمهور النموذج وحده، أو صاحب المحفوظ: دالة الاسم تُستدعى من المتصفح برقم أي حساب، فكانت تسرد أسماء
            // الحسابات كلها بالأرقام المتتالية (الاختبار الأمني 0.14.0)
            ->getOptionLabelUsing(fn (mixed $value, ?FormEntry $record): ?string => ((string) $record?->person_id === (string) $value
                ? User::query()
                : $audience->people())->whereKey($value)->value('name'))
            ->columnSpanFull();
    }

    /**
     * @param  array{key: string, label: string, type: DocumentFieldType, required: bool, options: list<string>, columns: list<string>, text: string, half: bool}  $field
     */
    private static function component(array $field, ?FormEntry $entry): Component
    {
        $key = $field['key'];

        if ($field['type'] === DocumentFieldType::Heading) {
            return Text::make($field['label'])->size(TextSize::Large)->weight(FontWeight::Bold)->columnSpanFull();
        }

        if ($field['type'] === DocumentFieldType::Paragraph) {
            return Text::make($field['text'])->columnSpanFull();
        }

        $component = match ($field['type']) {
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
            DocumentFieldType::Table => Repeater::make('answers.'.$key)
                ->schema(array_map(
                    fn (string $column, string $label): TextInput => TextInput::make($column)->label($label)->maxLength(255),
                    DocumentFields::columnKeys($field),
                    $field['columns'],
                ))
                ->columns(count($field['columns']))
                ->defaultItems($field['required'] ? 1 : 0)
                ->maxItems(DocumentFields::MAX_TABLE_ROWS)
                ->addActionLabel(__('documents.fields.table_add_row'))
                ->reorderable(false),
            default => TextInput::make('answers.'.$key)->maxLength(255),
        };

        return $component->label($field['label'])->required($field['required'])->columnSpan($field['half'] ? 1 : 'full');
    }
}
