<?php

namespace App\Filament\Forms;

use App\Support\ContentBlocks;
use Filament\Forms\Components\FileUpload;
use Illuminate\Database\Eloquent\Model;

/**
 * صورة داخل مقطع من مقاطع المحرر. منع التلاعب بالمسار في Filament يقبل ما كان محفوظاً في
 * عمود باسم الحقل، والصورة هنا داخل عمود المقاطع (blocks)، فالمحفوظ صور مقاطع السجل نفسه.
 * وما سواها يجب أن يُرفع الآن.
 */
final class BlockPictureUpload extends FileUpload
{
    /**
     * @return array<string>
     */
    public function getOriginalFilePaths(): array
    {
        $record = $this->getRecord();

        return $record instanceof Model ? ContentBlocks::images($record->getOriginal('blocks')) : [];
    }
}
