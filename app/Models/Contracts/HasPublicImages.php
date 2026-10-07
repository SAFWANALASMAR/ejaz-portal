<?php

namespace App\Models\Contracts;

/**
 * سجل محتوى له صور على القرص العام (App\Support\PublicImages): تُنظَّف الجديدة قبل نشرها،
 * وتُحذف التي خرجت منه.
 */
interface HasPublicImages
{
    /**
     * مجلد صور السجل على القرص العام. لا يمس الحفظ ولا الحذف ملفاً خارجه.
     */
    public function imageDirectory(): string;

    /**
     * صور السجل لكل حقل في النموذج، فيظهر خطأ الصورة تحت حقلها.
     *
     * @return array<string, list<string>>
     */
    public function publicImages(): array;
}
