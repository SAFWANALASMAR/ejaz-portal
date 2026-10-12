<?php

namespace App\Support;

use App\Enums\DocumentFieldType;
use App\Models\FormEntry;
use App\Models\Setting;

/**
 * المعبأ كما يُطبع على الورق (requirements/12 البند 10): كتل بترتيب حقول إصداره — عنوان قسم، ونص ثابت،
 * وصف بخلية أو خليتين (حقلان متتاليان بنصف العرض)، وجدول. ومعها بيانات الترويسة. مصدر واحد لطباعة
 * المتصفح وPDF (PrintFormEntry)، فلا يختلف شكلهما.
 */
final class FormEntryLayout
{
    /**
     * @return list<array{type: 'heading'|'paragraph', text: string}|array{type: 'row', cells: list<array{label: string, value: ?string, signature: ?string}>}|array{type: 'table', label: string, columns: list<string>, rows: list<list<string>>}>
     */
    public static function blocks(FormEntry $entry): array
    {
        $blocks = [];
        $pending = null;

        foreach ($entry->version->typedFields() as $field) {
            $type = $field['type'];

            if ($type->isLayout() || $type === DocumentFieldType::Table) {
                if ($pending !== null) {
                    $blocks[] = ['type' => 'row', 'cells' => [$pending]];
                    $pending = null;
                }

                $blocks[] = match ($type) {
                    DocumentFieldType::Heading => ['type' => 'heading', 'text' => $field['label']],
                    DocumentFieldType::Paragraph => ['type' => 'paragraph', 'text' => $field['text']],
                    default => [
                        'type' => 'table',
                        'label' => $field['label'],
                        'columns' => $field['columns'],
                        'rows' => array_map(
                            fn (array $row): array => array_map(fn (string $column): string => (string) ($row[$column] ?? ''), DocumentFields::columnKeys($field)),
                            (array) ($entry->answers[$field['key']] ?? []),
                        ),
                    ],
                };

                continue;
            }

            $cell = self::cell($entry, $field['key'], $field['label'], $type);

            if (! $field['half']) {
                if ($pending !== null) {
                    $blocks[] = ['type' => 'row', 'cells' => [$pending]];
                    $pending = null;
                }

                $blocks[] = ['type' => 'row', 'cells' => [$cell]];
            } elseif ($pending === null) {
                $pending = $cell;
            } else {
                $blocks[] = ['type' => 'row', 'cells' => [$pending, $cell]];
                $pending = null;
            }
        }

        if ($pending !== null) {
            $blocks[] = ['type' => 'row', 'cells' => [$pending]];
        }

        return $blocks;
    }

    /**
     * بيانات الترويسة: اسم الجمعية، وبيانات التواصل من الإعدادات وحدها (لا تُخترع). وما لم يُضبط منها
     * كله تظهر مكانه علامة الناقص.
     *
     * @return array{name: string, contact: list<string>}
     */
    public static function letterhead(): array
    {
        $settings = Setting::group('contact');

        $contact = array_values(array_filter(
            [$settings['address'] ?? null, $settings['phone'] ?? null, $settings['email'] ?? null],
            fn (mixed $value): bool => is_string($value) && trim($value) !== '',
        ));

        return ['name' => __('site.name'), 'contact' => $contact];
    }

    /**
     * ما يأخذه عرض الطباعة في المتصفح (documents/form-entry-print).
     *
     * @return array<string, mixed>
     */
    public static function viewData(FormEntry $entry): array
    {
        $entry->loadMissing(['version.template', 'creator', 'approver', 'person']);

        return ['entry' => $entry, 'blocks' => self::blocks($entry), 'letterhead' => self::letterhead()];
    }

    /**
     * الشعار الرسمي (public/images/brand) مساراً على القرص لـ mPDF.
     */
    public static function logoPath(): string
    {
        return public_path('images/brand/logo-horizontal.svg');
    }

    /**
     * @return array{label: string, value: ?string, signature: ?string}
     */
    private static function cell(FormEntry $entry, string $key, string $label, DocumentFieldType $type): array
    {
        $signature = $type === DocumentFieldType::Signature ? collect($entry->signatures ?? [])->firstWhere('key', $key) : null;

        return [
            'label' => $label,
            'value' => match ($type) {
                DocumentFieldType::File => collect($entry->files ?? [])->firstWhere('key', $key)['name'] ?? null,
                DocumentFieldType::Signature => null,
                default => is_scalar($entry->answers[$key] ?? null) ? (string) $entry->answers[$key] : null,
            },
            'signature' => $signature === null ? null : SignatureImage::dataUrl(FormEntry::disk(), $signature['path']),
        ];
    }
}
