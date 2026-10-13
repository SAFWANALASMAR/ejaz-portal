<?php

namespace App\Filament\Concerns;

use App\Actions\Content\SaveContent;
use App\Models\Contracts\HasPublicImages;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * صفحات إنشاء المحتوى وتعديله في اللوحة تحفظ عبر SaveContent (SavesThroughAction).
 */
trait SavesContentThroughAction
{
    use SavesThroughAction;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function saveContent(Model&HasPublicImages $record, array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();

        // صور المقاطع داخل محررها، فيُزال الملف المرفوض منه يدوياً برسالة الخطأ
        $imageFields = array_values(array_diff(array_keys($record->publicImages()), ['blocks']));

        return $this->saveThroughAction($record, $imageFields, fn (): Model => app(SaveContent::class)->handle($actor, $record, $data));
    }
}
