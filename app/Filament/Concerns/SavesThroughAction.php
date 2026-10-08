<?php

namespace App\Filament\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * صفحات الإنشاء والتعديل في اللوحة تحفظ عبر إجراء الوحدة، وتعرض أخطاءه تحت حقولها (حقول
 * النموذج تحت data). والملف المرفوض حذفه الإجراء، فيعود حقله إلى المحفوظ ليُحفظ النموذج بعدها.
 */
trait SavesThroughAction
{
    /**
     * @template TModel of Model
     *
     * @param  list<string>  $fileFields  حقول الملفات في النموذج
     * @param  Closure(): TModel  $save
     * @return TModel
     */
    protected function saveThroughAction(Model $record, array $fileFields, Closure $save): Model
    {
        try {
            return $save();
        } catch (ValidationException $exception) {
            foreach (array_intersect(array_keys($exception->errors()), $fileFields) as $field) {
                $this->data[$field] = collect((array) $record->getOriginal($field))
                    // والملفات المحفوظة بأسمائها ([path, name]) تعود بمساراتها
                    ->map(fn ($file) => is_array($file) ? ($file['path'] ?? null) : $file)
                    ->filter(fn ($path): bool => is_string($path) && $path !== '')
                    ->mapWithKeys(fn (string $path): array => [(string) Str::uuid() => $path])
                    ->all();
            }

            throw ValidationException::withMessages(
                collect($exception->errors())->mapWithKeys(fn (array $messages, string $key): array => ['data.'.$key => $messages])->all(),
            );
        }
    }
}
