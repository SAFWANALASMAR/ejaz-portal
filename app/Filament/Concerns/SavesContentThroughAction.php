<?php

namespace App\Filament\Concerns;

use App\Actions\Content\SaveContent;
use App\Models\Contracts\HasPublicImages;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * صفحات إنشاء المحتوى وتعديله في اللوحة تحفظ عبر SaveContent، وتعرض أخطاءه تحت حقولها
 * (حقول النموذج تحت data).
 */
trait SavesContentThroughAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function saveContent(Model&HasPublicImages $record, array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();

        try {
            return app(SaveContent::class)->handle($actor, $record, $data);
        } catch (ValidationException $exception) {
            // الصورة المرفوضة حُذفت، فيعود حقلها إلى صوره المحفوظة ليُحفظ النموذج بعدها. وصور
            // المقاطع داخل محررها، فيُزال الملف المرفوض منه يدوياً برسالة الخطأ
            $imageFields = array_diff(array_keys($record->publicImages()), ['blocks']);

            foreach (array_intersect(array_keys($exception->errors()), $imageFields) as $field) {
                $this->data[$field] = collect(array_filter((array) $record->getOriginal($field), fn ($path) => is_string($path) && $path !== ''))
                    ->mapWithKeys(fn (string $path) => [(string) Str::uuid() => $path])
                    ->all();
            }

            throw ValidationException::withMessages(
                collect($exception->errors())->mapWithKeys(fn (array $messages, string $key) => ['data.'.$key => $messages])->all(),
            );
        }
    }
}
