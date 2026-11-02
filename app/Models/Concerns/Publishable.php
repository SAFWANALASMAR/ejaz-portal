<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * نموذج له حالة نشر (`is_published`): نطاق `published()` يعيد المنشور وحده. والنموذج الذي يرتّب مع
 * النشر (المبادرات، والشركاء) يُبقي نطاقه.
 *
 * ولا يُنشر عنوان أو ملخص فيه علامة النص الناقص «【» (CLAUDE.md، القاعدة ١)، والمسودة تُحفظ بها. والفحص هنا عند الحفظ،
 * فيشمل نماذج اللوحة وكل طريق آخر إلى النشر. ومفتاح الخطأ اسم الحقل، ونموذج اللوحة يضيف إليه مساره فيظهر تحت الحقل
 * (docs/decisions/2026-11-03-seo-quality.md).
 */
trait Publishable
{
    /**
     * علامة النص الناقص التي تُكتب مكان ما لم تعتمده الجمعية.
     */
    public const MISSING_TEXT_MARK = '【';

    public static function bootPublishable(): void
    {
        static::saving(function (Model $model): void {
            // عند النشر أو تغيّر النص وحدهما، فلا يتعطل حفظ آخر (عدّاد أو ملف) لمنشور قديم
            if (! (bool) $model->getAttribute('is_published') || ! $model->isDirty(['is_published', 'title', 'summary'])) {
                return;
            }

            $attributes = $model->getAttributes();
            $messages = [];

            foreach (['title', 'summary'] as $field) {
                if (is_string($attributes[$field] ?? null) && str_contains($attributes[$field], self::MISSING_TEXT_MARK)) {
                    $messages[$field] = __('content.missing_text_blocks_publish');
                }
            }

            if ($messages !== []) {
                throw ValidationException::withMessages($messages);
            }
        });
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }
}
