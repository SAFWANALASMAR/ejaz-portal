<?php

namespace App\Actions\Documents;

use App\Enums\ArchiveItemType;
use App\Models\ArchiveCategory;
use App\Models\ArchiveItem;
use App\Models\User;
use App\Support\ImageSanitizer;
use App\Support\PrivatePdf;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * حفظ عنصر في الأرشيف (السكرتير — ArchiveItemPolicy): في تصنيف يطّلع عليه الحافظ، وملفاته PDF حقيقية
 * أو صور يُعاد ترميزها. والمراسلة الجديدة تأخذ رقم قيد تسلسلياً في نوعها وسنتها من عدّاد يُقفل صفه في
 * معاملة الحفظ، فلا يتكرر ولو تزامن إدخالان (وقيد فريد في القاعدة). ونوع المراسلة وسنتها لا يتغيران بعده.
 */
class SaveArchiveItem
{
    private const MAX_FILES = 20;

    private const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, ArchiveItem $item, array $data): ArchiveItem
    {
        $allowed = $item->exists ? $actor->can('update', $item) : $actor->can('create', ArchiveItem::class);

        if (! $allowed) {
            throw new AuthorizationException;
        }

        $validated = Validator::make($data, [
            'type' => [Rule::requiredIf(! $item->exists), Rule::enum(ArchiveItemType::class)],
            'archive_category_id' => ['required', 'integer', Rule::exists('archive_categories', 'id')],
            'title' => ['required', 'string', 'max:250'],
            'entity' => ['nullable', 'string', 'max:200'],
            'dated_on' => ['required', 'date'],
            'program_id' => ['nullable', 'integer', Rule::exists('programs', 'id')],
            'description' => ['nullable', 'string', 'max:5000'],
            'files' => ['array', 'max:'.self::MAX_FILES],
            'files.*.path' => ['required', 'string', 'max:255'],
            'files.*.name' => ['required', 'string', 'max:200'],
        ])->validate();

        // لا يُحفظ عنصر في تصنيف لا يطّلع عليه الحافظ
        if (! ArchiveCategory::query()->findOrFail($validated['archive_category_id'])->allows($actor)) {
            throw new AuthorizationException;
        }

        $type = $item->exists ? $item->type : self::type($validated['type']);
        $year = Carbon::parse($validated['dated_on'])->year;

        if ($item->exists && $type->numbered() && $year !== $item->year) {
            throw ValidationException::withMessages(['dated_on' => __('documents.validation.numbered_year')]);
        }

        $before = collect($item->files ?? [])->keyBy('path');
        $files = array_map(fn (array $file): array => $before->get($file['path']) ?? self::newFile($item, $file), $validated['files'] ?? []);

        DB::transaction(function () use ($actor, $item, $validated, $type, $year, $files): void {
            $item->fill([
                'archive_category_id' => $validated['archive_category_id'],
                'title' => $validated['title'],
                'entity' => $validated['entity'] ?? null,
                'dated_on' => $validated['dated_on'],
                'program_id' => $validated['program_id'] ?? null,
                'description' => $validated['description'] ?? null,
            ]);
            $item->year = $year;
            $item->files = $files === [] ? null : array_values($files);

            if (! $item->exists) {
                $item->type = $type;
                $item->created_by = $actor->id;
                $item->registration_number = $type->numbered() ? self::nextNumber($type, $year) : null;
            }

            $item->save();
        });

        $kept = array_column($files, 'path');
        $removed = $before->keys()->reject(fn (string $path): bool => in_array($path, $kept, true) || ! PrivatePdf::inDirectory($path, ArchiveItem::DIRECTORY));
        Storage::disk(ArchiveItem::disk())->delete($removed->values()->all());

        return $item;
    }

    /**
     * الرقم التالي في النوع والسنة: صف العدّاد يُقفل حتى اعتماد المعاملة، فينتظر الإدخال المتزامن ويأخذ
     * ما بعده. وأول مراسلة في السنة تنشئ الصف، والمتزامنان على إنشائه يلتقيان على قيده الفريد.
     */
    private static function nextNumber(ArchiveItemType $type, int $year): int
    {
        DB::table('archive_sequences')->insertOrIgnore(['type' => $type->value, 'year' => $year, 'next' => 1]);

        $sequence = DB::table('archive_sequences')->where('type', $type->value)->where('year', $year)->lockForUpdate()->first();
        $number = (int) $sequence->next;

        DB::table('archive_sequences')->where('id', $sequence->id)->update(['next' => $number + 1]);

        return $number;
    }

    private static function type(mixed $type): ArchiveItemType
    {
        return $type instanceof ArchiveItemType ? $type : ArchiveItemType::from((string) $type);
    }

    /**
     * @param  array{path: string, name: string}  $file
     * @return array{path: string, name: string, size: int}
     *
     * @throws ValidationException
     */
    private static function newFile(ArchiveItem $item, array $file): array
    {
        $path = $file['path'];

        // ملف عنصر آخر (ولو محذوف ناعماً) لا يُعاد استعماله، فلا يحذفه حذفُه منه
        $inUse = ArchiveItem::withTrashed()
            ->whereJsonContains('files', [['path' => $path]])
            ->when($item->exists, fn ($query) => $query->whereKeyNot($item->getKey()))
            ->exists();

        if ($inUse) {
            throw ValidationException::withMessages(['files' => __('validation.file_in_use')]);
        }

        $disk = Storage::disk(ArchiveItem::disk());
        $type = PrivatePdf::inDirectory($path, ArchiveItem::DIRECTORY) && $disk->exists($path)
            ? (string) (new \finfo(FILEINFO_MIME_TYPE))->buffer((string) $disk->get($path))
            : '';

        if (in_array($type, self::IMAGE_TYPES, true)) {
            try {
                ImageSanitizer::strip(ArchiveItem::disk(), $path);
            } catch (RuntimeException) {
                $disk->delete($path);

                throw ValidationException::withMessages(['files' => __('documents.validation.invalid_file')]);
            }

            $size = (int) $disk->size($path);
        } elseif ($type === 'application/pdf') {
            $size = PrivatePdf::check(ArchiveItem::disk(), ArchiveItem::DIRECTORY, $path, 'files');
        } else {
            if (PrivatePdf::inDirectory($path, ArchiveItem::DIRECTORY)) {
                $disk->delete($path);
            }

            throw ValidationException::withMessages(['files' => __('documents.validation.invalid_file')]);
        }

        return ['path' => $path, 'name' => str_replace(['/', '\\'], '-', $file['name']), 'size' => $size];
    }
}
