<?php

namespace App\Actions\Documents;

use App\Enums\ActivityEvent;
use App\Enums\DocumentFieldType;
use App\Filament\Exports\ServiceRequestExporter;
use App\Models\FormEntry;
use App\Models\FormTemplate;
use App\Models\User;
use App\Support\DocumentFields;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * «إمكانية تصدير البيانات عند الحاجة» (requirements/12 البند 10): معبأ نموذج واحد CSV يقرؤه Excel بالعربية،
 * بنهج تصدير نتائج الاستبيانات (يُبث صفاً صفاً، والخلية التي تبدأ بـ = + - @ تُحيَّد). أعمدة الإجابات
 * باتحاد حقول إصداراته (التخطيط مستبعد)، فلا يضيع حقل حُذف من الإصدار الحالي. بصلاحية
 * form_entries.export، ويُسجَّل بالنموذج والعدد.
 */
class ExportFormEntries
{
    /**
     * @throws AuthorizationException
     */
    public function handle(User $actor, FormTemplate $template): StreamedResponse
    {
        if (! $actor->can('export', FormEntry::class) || ! $actor->can('view', $template)) {
            throw new AuthorizationException;
        }

        $fields = self::fields($template);
        $query = FormEntry::query()
            ->whereIn('form_template_version_id', $template->versions()->select('id'))
            ->with(['version', 'person', 'creator']);

        activity('documents')->causedBy($actor)->performedOn($template)
            ->event(ActivityEvent::FormEntriesExported->value)
            ->withProperties(['count' => (clone $query)->count()])
            ->log(ActivityEvent::FormEntriesExported->value);

        return response()->streamDownload(function () use ($query, $fields): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            // علامة الترميز ليقرأ Excel العربية
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, self::safe([
                __('documents.fields.reference'), __('documents.fields.version'), __('documents.fields.status'),
                __('documents.fields.person'), __('documents.fields.created_by'), __('documents.fields.created_at'),
                __('documents.fields.approved_at'),
                ...array_column($fields, 'label'),
            ]), escape: '');

            foreach ($query->lazyById(500) as $entry) {
                fputcsv($out, self::safe([
                    $entry->reference,
                    (string) $entry->version->version,
                    $entry->status->getLabel(),
                    $entry->person->name ?? '',
                    $entry->creator->name ?? '',
                    self::time($entry->created_at),
                    self::time($entry->approved_at),
                    ...array_map(fn (array $field): string => self::value($entry, $field), $fields),
                ]), escape: '');
            }

            fclose($out);
        }, 'form-'.$template->id.'-entries.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * حقول الإجابة في كل الإصدارات بمفاتيحها، واسم الحقل وتعريفه من أحدث إصدار ذُكر فيه.
     *
     * @return list<array{key: string, label: string, type: DocumentFieldType, columns: list<string>}>
     */
    private static function fields(FormTemplate $template): array
    {
        $fields = [];

        foreach ($template->versions()->get() as $version) {
            foreach ($version->typedFields() as $field) {
                if (! $field['type']->isLayout()) {
                    $fields[$field['key']] = ['key' => $field['key'], 'label' => $field['label'], 'type' => $field['type'], 'columns' => $field['columns']];
                }
            }
        }

        return array_values($fields);
    }

    /**
     * @param  array{key: string, label: string, type: DocumentFieldType, columns: list<string>}  $field
     */
    private static function value(FormEntry $entry, array $field): string
    {
        $key = $field['key'];

        return match ($field['type']) {
            DocumentFieldType::File => (string) (collect($entry->files ?? [])->firstWhere('key', $key)['name'] ?? ''),
            DocumentFieldType::Signature => collect($entry->signatures ?? [])->contains('key', $key) ? __('documents.export.signed') : '',
            DocumentFieldType::Table => collect((array) ($entry->answers[$key] ?? []))
                ->map(fn (mixed $row): string => implode(' — ', array_map(fn (string $column): string => (string) (((array) $row)[$column] ?? ''), DocumentFields::columnKeys($field))))
                ->implode("\n"),
            default => (string) ($entry->answers[$key] ?? ''),
        };
    }

    private static function time(?\DateTimeInterface $time): string
    {
        return $time === null ? '' : Carbon::instance($time)->setTimezone((string) config('app.display_timezone'))->format('Y-m-d H:i');
    }

    /**
     * @param  list<string>  $row
     * @return list<string>
     */
    private static function safe(array $row): array
    {
        return array_map(fn (string $value): string => (string) ServiceRequestExporter::safeCell($value), $row);
    }
}
