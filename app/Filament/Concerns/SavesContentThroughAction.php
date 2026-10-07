<?php

namespace App\Filament\Concerns;

use App\Actions\Content\SaveContent;
use App\Models\Contracts\HasPublicImages;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
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
            throw ValidationException::withMessages(
                collect($exception->errors())->mapWithKeys(fn (array $messages, string $key) => ['data.'.$key => $messages])->all(),
            );
        }
    }
}
