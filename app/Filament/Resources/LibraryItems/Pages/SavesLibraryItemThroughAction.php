<?php

namespace App\Filament\Resources\LibraryItems\Pages;

use App\Actions\Library\SaveLibraryItem;
use App\Filament\Concerns\SavesThroughAction;
use App\Models\LibraryItem;
use App\Models\User;

/**
 * صفحتا إنشاء العنصر وتعديله تحفظان عبر SaveLibraryItem (SavesThroughAction).
 */
trait SavesLibraryItemThroughAction
{
    use SavesThroughAction;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function saveLibraryItem(LibraryItem $item, array $data): LibraryItem
    {
        /** @var User $actor */
        $actor = auth()->user();

        return $this->saveThroughAction($item, ['thumbnail', 'file'], fn (): LibraryItem => app(SaveLibraryItem::class)->handle($actor, $item, $data));
    }
}
