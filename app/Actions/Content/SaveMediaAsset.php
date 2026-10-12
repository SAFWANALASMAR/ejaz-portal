<?php

namespace App\Actions\Content;

use App\Models\MediaAsset;
use App\Models\User;
use App\Rules\PlainText;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * تعديل عنصر في مكتبة الوسائط من اللوحة: العنوان والنص البديل وكونه زخرفياً. والملفات لا تُمس.
 * والتعديل يُسجَّل (LogsActivity).
 */
class SaveMediaAsset
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, MediaAsset $asset, array $data): MediaAsset
    {
        if (! $actor->can('update', $asset)) {
            throw new AuthorizationException;
        }

        $values = Validator::make($data, [
            'title' => ['required', 'string', 'max:200', new PlainText],
            'alt' => ['nullable', 'string', 'max:300', new PlainText],
            'is_decorative' => ['required', 'boolean'],
        ], attributes: [
            'title' => __('media_assets.fields.title'),
            'alt' => __('media_assets.fields.alt'),
        ])->validate();

        $decorative = (bool) $values['is_decorative'];

        $asset->fill([
            'title' => $values['title'],
            // الزخرفية نصها البديل فارغ
            'alt' => $decorative ? null : ($values['alt'] ?? null),
            'is_decorative' => $decorative,
        ])->save();

        return $asset;
    }
}
