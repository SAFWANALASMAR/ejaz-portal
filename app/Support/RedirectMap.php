<?php

namespace App\Support;

use App\Models\Redirect;

/**
 * خريطة التحويلات المفعّلة، مخزنة في ذاكرة المحتوى فيمسحها كل تعديل. يقرؤها المسار الاحتياطي
 * وحده (FallbackController)، أي طلب GET لا يطابق أي مسار، فلا تكلّف بقية الموقع شيئاً.
 */
final class RedirectMap
{
    /**
     * الصيغة الموحدة للمسار، كما يُحفظ المصدر ويُبحث عنه: بلا نطاق ولا استعلام، مفكوك الترميز،
     * بلا شرطة في طرفيه، بحروف صغيرة. فالرابط المنسوخ من المتصفح بترميزه، والمسار المكتوب
     * بحروفه العربية، والشرطة الأخيرة وغيابها، كلها مسار واحد.
     */
    public static function normalize(string $path): string
    {
        if (preg_match('#^https?://#i', $path) === 1) {
            $path = (string) parse_url($path, PHP_URL_PATH);
        }

        $path = substr($path, 0, strcspn($path, '?#'));

        return mb_strtolower(trim(rawurldecode(trim($path)), '/'));
    }

    /**
     * الوجهة مسار داخلي بحروف لاتينية ظاهرة: لا نطاق، ولا «//» ولا «\» يجعلها المتصفح نطاقاً آخر.
     */
    public static function isSafeTarget(string $target): bool
    {
        return str_starts_with($target, '/') && ! str_starts_with($target, '//') && ! str_contains($target, '\\')
            && preg_match('/^[\x21-\x7E]+$/', $target) === 1;
    }

    /**
     * الوجهة ورمز الرد لمسار طلب، أو null إن لم يكن له تحويل مفعّل.
     *
     * @return array{0: string, 1: int}|null
     */
    public static function find(string $path): ?array
    {
        /** @var array<string, array{0: string, 1: int}> $map */
        $map = ContentCache::remember('redirects', fn (): array => Redirect::query()
            ->where('is_active', true)
            ->get(['source', 'target', 'type'])
            // ولو وصلت وجهة غير آمنة إلى القاعدة من غير SaveRedirect فلا تحويل مفتوحاً إليها
            ->filter(fn (Redirect $redirect): bool => self::isSafeTarget($redirect->target))
            ->mapWithKeys(fn (Redirect $redirect): array => [$redirect->source => [$redirect->target, $redirect->type->statusCode()]])
            ->all());

        return $map[self::normalize($path)] ?? null;
    }
}
