<?php

namespace App\Models;

use App\Models\Concerns\FlushesContentCache;
use App\Models\Concerns\Publishable;
use App\Models\Contracts\HasPublicImages;
use App\Support\ContentBlocks;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * صفحة محتوى بمقاطع مرنة يحررها المحرر من اللوحة. و«عن الجمعية» صفحة بالمعرّف about،
 * لا تُحذف ولا يتغير معرّفها، لأن رابطها مبني عليه.
 *
 * وصفحات «المقدمة» (INTROS) نصوص صفحات الموقع السابق التي صار لها قالب مبني في البوابة: تُعرض
 * مقاطعها أعلى صفحة القالب (x-page-intro)، ورابطها /pages/{slug} يحوّل إليه.
 */
#[Fillable(['slug', 'title', 'summary', 'blocks', 'is_published'])]
class Page extends Model implements HasPublicImages
{
    /** @use HasFactory<PageFactory> */
    use FlushesContentCache, HasFactory, LogsActivity, Publishable;

    public const ABOUT = 'about';

    public const IMAGE_DIRECTORY = 'pages';

    /**
     * معرّف صفحة المقدمة ← اسم مسار القالب الذي تُعرض فيه ومعاملاته.
     *
     * @var array<string, array{0: string, 1: array<string, string>}>
     */
    public const INTROS = [
        'contact' => ['contact', []],
        'inquiries' => ['contact', []],
        'assembly-membership' => ['services.assembly-membership', []],
        'jobs' => ['services.jobs', []],
        'volunteering' => ['services.volunteering', []],
        'reports' => ['governance.index', ['category' => 'annual-reports']],
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'is_published' => 'boolean',
        ];
    }

    /**
     * رابط القالب الذي تُعرض فيه صفحة المقدمة، أو null إن لم تكن منها.
     */
    public static function introUrl(string $slug, bool $absolute = true): ?string
    {
        if (! isset(self::INTROS[$slug])) {
            return null;
        }

        [$route, $parameters] = self::INTROS[$slug];

        return route($route, $parameters, $absolute);
    }

    public function imageDirectory(): string
    {
        return self::IMAGE_DIRECTORY;
    }

    /**
     * @return array<string, list<string>>
     */
    public function publicImages(): array
    {
        return ['blocks' => ContentBlocks::images($this->blocks)];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
