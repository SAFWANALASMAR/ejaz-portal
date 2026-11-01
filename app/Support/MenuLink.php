<?php

namespace App\Support;

use App\Rules\SafeUrl;
use Illuminate\Support\Collection;

/**
 * عنصر قائمة جاهز للعرض. يُبنى من مصفوفة مخزنة في الذاكرة المؤقتة، لأن لارافيل 13
 * يمنع استرجاع الكائنات من الذاكرة المؤقتة (cache.serializable_classes = false).
 */
final readonly class MenuLink
{
    /**
     * @param  Collection<int, MenuLink>  $children
     * @param  bool  $linkable  false لعنصر يشير إلى صفحة غير منشورة ويبقى لعناصره الفرعية: زر القائمة
     *                          المنسدلة في الرأس، ولا رابط له في التذييل (SiteChromeComposer)
     */
    public function __construct(
        public int $id,
        public string $label,
        public string $url,
        public bool $opensInNewTab,
        public Collection $children,
        public bool $linkable = true,
    ) {}

    /**
     * @param  array{id: int, label: string, url: ?string, opens_in_new_tab: bool, linkable?: bool, children?: list<array<string, mixed>>}  $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            id: $row['id'],
            label: $row['label'],
            url: (string) $row['url'],
            opensInNewTab: $row['opens_in_new_tab'],
            children: collect($row['children'] ?? [])->map(fn (array $child): self => self::fromArray($child)),
            linkable: $row['linkable'] ?? true,
        );
    }

    public function href(): string
    {
        // دفاع إضافي: لا يُعرض رابط غير آمن حتى لو وصل إلى القاعدة دون تحقق
        if (! SafeUrl::isSafe($this->url)) {
            return '#';
        }

        return str_starts_with($this->url, '/') ? url($this->url) : $this->url;
    }

    /**
     * يتنقّل إليه الزائر بلا إعادة تحميل (wire:navigate): صفحة داخلية لا تُفتح في تبويب جديد (SpaLink).
     */
    public function navigates(): bool
    {
        return ! $this->opensInNewTab && SpaLink::allows($this->href());
    }

    /**
     * أقسام يُعلَّم فيها عنصر قسم آخر نشطاً: «الخدمات الإلكترونية» تضم الدورات والبرامج والمسابقات والمبادرات (أمر التصور النهائي،
     * النسخة 63 — البند R4)، فهي النشطة في صفحاتها.
     *
     * @var array<string, list<string>>
     */
    public const CURRENT_IN = [
        'services' => ['programs', 'competitions', 'initiatives'],
    ];

    public function isCurrent(): bool
    {
        if (! str_starts_with($this->url, '/')) {
            return false;
        }

        $path = trim($this->url, '/');

        if ($path === '') {
            return request()->is('/');
        }

        // رابط باستعلام (تصنيف في الحوكمة): نشط في صفحته نفسها بقيم استعلامه وحدها
        if (str_contains($path, '?')) {
            return $this->isExactlyCurrent();
        }

        $patterns = [$path, $path.'/*'];

        foreach (self::CURRENT_IN[$path] ?? [] as $section) {
            array_push($patterns, $section, $section.'/*');
        }

        return request()->is(...$patterns);
    }

    /**
     * الصفحة نفسها لا ما تحتها: أول عنصر في القائمة المنسدلة (الصفحة الأم — MenuSections) نشط فيها وحدها، فلا يُعلَّم مع قسمها
     * النشط. والاستعلام إن كان في الرابط يطابق قيمه في الطلب.
     */
    public function isExactlyCurrent(): bool
    {
        if (! str_starts_with($this->url, '/')) {
            return false;
        }

        $path = trim((string) parse_url($this->url, PHP_URL_PATH), '/');
        parse_str((string) parse_url($this->url, PHP_URL_QUERY), $query);

        if (! request()->is($path === '' ? '/' : $path)) {
            return false;
        }

        foreach ($query as $key => $value) {
            if (request()->query((string) $key) !== $value) {
                return false;
            }
        }

        // والصفحة الأم بلا استعلام ليست النشطة إن كان التصنيف هو المعروض
        return $query !== [] || ! request()->has('category');
    }

    public function hasCurrentChild(): bool
    {
        return $this->children->contains(fn (MenuLink $child): bool => $child->isCurrent());
    }
}
