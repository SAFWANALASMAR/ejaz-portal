<?php

namespace App\Models;

use App\Enums\AssistantMode;
use App\Enums\AssistantRating;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * محادثة واحدة مع المساعد للمراجعة: السؤال (بعد حذف ما يشبه البيانات الشخصية)، والجواب، والمصادر، والتقييم،
 * والتكلفة التقديرية. بلا عنوان IP ولا معرّف مستخدم ولا جلسة (requirements/10)، ويُحذف بعد مدة الاحتفاظ.
 *
 * @property int $id
 * @property string $uuid
 * @property string $question
 * @property string|null $answer
 * @property list<array{title: string, url: string}>|null $sources
 * @property AssistantMode $mode
 * @property AssistantRating|null $rating
 * @property bool $is_flagged
 * @property string|null $flag_note
 * @property int $input_tokens
 * @property int $output_tokens
 * @property string $cost
 * @property string|null $provider
 * @property string|null $model
 * @property Carbon $created_at
 */
#[Fillable(['question', 'answer', 'sources', 'mode', 'rating', 'is_flagged', 'flag_note', 'input_tokens', 'output_tokens', 'cost', 'provider', 'model'])]
class AssistantLog extends Model
{
    // حذف بالجملة بلا أحداث لكل سجل، كسجل الرسائل (مراجعة ما بعد v0.12.0)
    use HasUuids, MassPrunable;

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sources' => 'array',
            'mode' => AssistantMode::class,
            'rating' => AssistantRating::class,
            'is_flagged' => 'boolean',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'cost' => 'decimal:6',
        ];
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays(AssistantSettings::current()->retentionDays()));
    }
}
