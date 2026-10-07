<?php

namespace App\Filament\Actions;

use App\Actions\Content\DeleteContent;
use App\Models\Contracts\HasPublicImages;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Illuminate\Database\Eloquent\Model;

/**
 * حذف سجل محتوى عبر DeleteContent، فتُحذف صوره معه ولا تبقى منشورة.
 */
final class DeleteContentAction
{
    public static function make(): DeleteAction
    {
        return DeleteAction::make()->using(function (Model $record): bool {
            /** @var User $actor */
            $actor = auth()->user();

            if ($record instanceof HasPublicImages) {
                app(DeleteContent::class)->handle($actor, $record);
            }

            return true;
        });
    }
}
