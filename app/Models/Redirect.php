<?php

namespace App\Models;

use App\Enums\RedirectType;
use App\Models\Concerns\FlushesContentCache;
use Database\Factories\RedirectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * تحويل رابط قديم إلى مسار في البوابة. يُطابَق في المسار الاحتياطي وحده (RedirectController)،
 * فلا يمس طلباً له مسار. والحفظ عبر SaveRedirect، والتعديل يمسح الخريطة المخزنة.
 *
 * @property string $source
 * @property string $target
 * @property RedirectType $type
 * @property bool $is_active
 */
#[Fillable(['source', 'target', 'type', 'is_active'])]
class Redirect extends Model
{
    /** @use HasFactory<RedirectFactory> */
    use FlushesContentCache, HasFactory, LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => RedirectType::class,
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
