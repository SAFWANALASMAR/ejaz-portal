<?php

namespace App\Actions\Content;

use App\Models\Contracts\HasPublicImages;
use App\Models\User;
use App\Support\PublicImages;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * حذف سجل محتوى مع صوره، فلا تبقى منشورة على روابطها. والمنع الصريح في السياسة يسري
 * (صفحة «عن الجمعية» لا تُحذف).
 */
class DeleteContent
{
    /**
     * @throws AuthorizationException
     */
    public function handle(User $actor, Model&HasPublicImages $record): void
    {
        if (! $actor->can('delete', $record)) {
            throw new AuthorizationException;
        }

        $images = array_merge(...array_values($record->publicImages()));

        DB::transaction(fn () => $record->delete());

        PublicImages::discard($images, [], $record->imageDirectory());
    }
}
