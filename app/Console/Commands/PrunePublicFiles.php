<?php

namespace App\Console\Commands;

use App\Actions\Settings\UpdateSiteSettings;
use App\Models\Album;
use App\Models\BoardMember;
use App\Models\Contracts\HasPublicImages;
use App\Models\Initiative;
use App\Models\LibraryItem;
use App\Models\MediaAsset;
use App\Models\NewsArticle;
use App\Models\Page;
use App\Models\Partner;
use App\Models\Program;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * يحذف من القرص العام الصور التي لا يشير إليها سجل بعد مدة (docs/plans/prune-public-files.md): حقل الرفع
 * ينقل الصورة إلى مجلدها عند الحفظ، فإن فشل حفظ السجل بقيت منشورة بلا سجل. والحذف محصور في مجلدات
 * السجلات المعروفة، والمدة تحمي ما يُرفع الآن ولم يُحفظ سجله بعد.
 */
class PrunePublicFiles extends Command
{
    /**
     * النماذج التي تطبّق HasPublicImages. ويختبر PrunePublicFilesTest ألا يغيب عنها نموذج.
     *
     * @var list<class-string<Model&HasPublicImages>>
     */
    public const MODELS = [Album::class, BoardMember::class, Initiative::class, LibraryItem::class, NewsArticle::class, Page::class, Partner::class, Program::class];

    protected $signature = 'ejaz:prune-public-files {--dry-run : عدّ ما سيُحذف دون حذفه}';

    protected $description = 'حذف صور القرص العام التي لا يشير إليها سجل بعد مدة';

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $before = now()->subDays(max(1, (int) config('filesystems.public_orphan_days')))->getTimestamp();
        $referenced = array_flip($this->referencedPaths());
        $orphans = [];

        foreach ($this->directories() as $directory) {
            foreach ($disk->allFiles($directory) as $path) {
                if (! isset($referenced[$path]) && ! str_starts_with(basename($path), '.') && $disk->lastModified($path) < $before) {
                    $orphans[] = $path;
                }
            }
        }

        if (! $this->option('dry-run') && $orphans !== []) {
            $disk->delete($orphans);
        }

        $message = $this->option('dry-run') ? 'سيُحذف '.count($orphans).' ملف غير مرتبط.' : 'حُذف '.count($orphans).' ملف غير مرتبط.';
        $this->info($message);
        Log::info('ejaz:prune-public-files', ['count' => count($orphans), 'dry_run' => (bool) $this->option('dry-run')]);

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function directories(): array
    {
        $directories = array_map(fn (string $model): string => (new $model)->imageDirectory(), self::MODELS);

        return array_values(array_unique([...$directories, UpdateSiteSettings::IMAGE_DIRECTORY, MediaAsset::WEB_DIRECTORY]));
    }

    /**
     * كل مسار يشير إليه سجل: صور السجلات (والمحذوفة ناعماً، لأنها تُستعاد)، وصورة الرئيسية، والنسخ المعروضة
     * في مكتبة الوسائط.
     *
     * @return list<string>
     */
    private function referencedPaths(): array
    {
        $paths = [];

        foreach (self::MODELS as $model) {
            $query = in_array(SoftDeletes::class, class_uses_recursive($model), true) ? $model::withTrashed() : $model::query();

            foreach ($query->lazyById(500) as $record) {
                foreach ($record->publicImages() as $images) {
                    array_push($paths, ...$images);
                }
            }
        }

        $hero = Setting::group('home')['hero_image'] ?? null;

        if (is_string($hero) && $hero !== '') {
            $paths[] = $hero;
        }

        return [...$paths, ...MediaAsset::query()->whereNotNull('web_path')->pluck('web_path')->all()];
    }
}
