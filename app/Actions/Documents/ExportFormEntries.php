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
 * form_entries.export، ويُسجَّل بالنموذج والعدد. والملف بما يطّلع عليه المصدِّر في اللوحة وحده: ما عبّأه،
 * أو الكل لمن يملك form_entries.view_all (FormEntry::scopeVisibleInPanel).
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
            ->visibleInPanel($actor)
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

            // كل معبأ بتعريف حقوله في إصداره هو (قد يتغير نوع الحقل أو أعمدة الجدول بين الإصدارات)
            $byVersion = [];

            foreach ($query->lazyById(500) as $entry) {
                $own = $byVersion[$entry->form_template_version_id] ??= collect($entry->version->typedFields())->keyBy('key')->all();

                fputcsv($out, self::safe([
                    $entry->reference,
                    (string) $entry->version->version,
                    $entry->status->getLabel(),
                    $entry->person->name ?? '',
                    $entry->creator->name ?? '',
                    self::time($entry->created_at),
                    self::time($entry->approved_at),
                    ...array_map(fn (array $field): string => isset($own[$field['key']]) ? self::value($entry, $own[$field['key']]) : '', $fields),
                ]), escape: '');
            }

            fclose($out);
        }, 'form-'.$template->id.'-entries.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * أعمدة الإجابة: حقول كل الإصدارات بمفاتيحها، واسم العمود من أحدث إصدار ذُكر فيه الحقل.
     *
     * @return list<array{key: string, label: string}>
     */
    private static function fields(FormTemplate $template): array
    {
        $fields = [];

        // versions() مرتبة بالإصدار، فالأحدث يكتب الاسم أخيراً
        foreach ($template->versions()->get() as $version) {
            foreach ($version->typedFields() as $field) {
                if (! $field['type']->isLayout()) {
                    $fields[$field['key']] = ['key' => $field['key'], 'label' => $field['label']];
                }
            }
        }

        return array_values($fields);
    }

    /**
     * @param  array{key: string, type: DocumentFieldType, columns: list<string>}  $field  تعريف الحقل في إصدار المعبأ
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
            default => is_scalar($entry->answers[$key] ?? null) ? (string) $entry->answers[$key] : '',
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
