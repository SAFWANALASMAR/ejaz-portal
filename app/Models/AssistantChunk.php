<?php

namespace App\Models;

use App\Enums\AssistantSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * مقطع في فهرس المساعد من المحتوى المنشور (AssistantIndex). والعمود search محسوب في القاعدة من النص المطبَّع.
 *
 * @property AssistantSource $source_type
 * @property int $source_id
 * @property int $position
 * @property string $title
 * @property string $url
 * @property string $body
 */
#[Fillable(['source_type', 'source_id', 'position', 'title', 'url', 'body', 'title_norm', 'body_norm'])]
class AssistantChunk extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_type' => AssistantSource::class,
            'source_id' => 'integer',
            'position' => 'integer',
        ];
    }
}
