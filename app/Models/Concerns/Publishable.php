<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * نموذج له حالة نشر (`is_published`): نطاق `published()` يعيد المنشور وحده. والنموذج الذي يرتّب مع
 * النشر (المبادرات، والشركاء) يُبقي نطاقه.
 *
 * ولا يُنشر نص فيه علامة النص الناقص «【» (CLAUDE.md، القاعدة ١)، والمسودة تُحفظ بها. والحقول في `$missingTextFields` لكل
 * نموذج: العنوان والملخص والمتن والوصف، وقيم حقول JSON (المقاطع) بما في داخلها (قرار المشرف على طلب الدمج 147). والفحص
 * هنا عند الحفظ، فيشمل نماذج اللوحة وكل طريق آخر إلى النشر. ومفتاح الخطأ اسم الحقل، ونموذج اللوحة يضيف إليه مساره فيظهر
 * تحت الحقل (docs/decisions/2026-11-03-seo-quality.md).
 *
 * والنموذج الذي يستعمله يعرّف `protected array $missingTextFields`.
 */
trait Publishable
{
    /**
     * علامة النص الناقص التي تُكتب مكان ما لم تعتمده الجمعية.
     */
    public const MISSING_TEXT_MARK = '【';

    public static function bootPublishable(): void
    {
        static::saving(function (self $model): void {
            $fields = $model->missingTextFields();

            // عند النشر أو تغيّر النص وحدهما، فلا يتعطل حفظ آخر (عدّاد أو ملف) لمنشور قديم
            if (! (bool) $model->getAttribute('is_published') || ! $model->isDirty(['is_published', ...$fields])) {
                return;
            }

            $attributes = $model->getAttributes();
            $messages = [];

            foreach ($fields as $field) {
                $value = $attributes[$field] ?? null;

                // حقل JSON محفوظ نصاً مرمّزاً (والعلامة فيه بترميز يونيكود U+3010)، فيُفك ويُفحص كل ما في داخله
                if (is_string($value) && $model->hasCast($field, ['array', 'json', 'object', 'collection'])) {
                    $value = json_decode($value, true);
                }

                if (self::hasMissingText($value)) {
                    $messages[$field] = __('content.missing_text_blocks_publish');
                }
            }

            if ($messages !== []) {
                throw ValidationException::withMessages($messages);
            }
        });
    }

    /**
     * @return list<string>
     */
    public function missingTextFields(): array
    {
        return $this->missingTextFields;
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    private static function hasMissingText(mixed $value): bool
    {
        if (is_string($value)) {
            return str_contains($value, self::MISSING_TEXT_MARK);
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if (self::hasMissingText($item)) {
                    return true;
                }
            }
        }

        return false;
    }
}
