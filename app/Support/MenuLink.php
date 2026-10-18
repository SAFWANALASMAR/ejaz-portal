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

    public function isCurrent(): bool
    {
        if (! str_starts_with($this->url, '/')) {
            return false;
        }

        $path = trim($this->url, '/');

        return $path === '' ? request()->is('/') : request()->is($path, $path.'/*');
    }

    public function hasCurrentChild(): bool
    {
        return $this->children->contains(fn (MenuLink $child): bool => $child->isCurrent());
    }
}
