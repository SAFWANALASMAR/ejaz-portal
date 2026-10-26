<?php

namespace App\Console\Commands;

use App\Actions\Settings\UpdateSiteSettings;
use App\Models\Album;
use App\Models\Announcement;
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
use App\Support\HomeContent;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * يحذف من القرص العام الصور التي لا يشير إليها سجل بعد مدة (docs/plans/prune-public-files.md): حقل الرفع
 * ينقل الصورة إلى مجلدها عند الحفظ، فإن فشل حفظ السجل بقيت منشورة بلا سجل. والحذف محصور في مجلدات
 * السجلات المعروفة، والمدة تحمي ما يُرفع الآن ولم يُحفظ سجله بعد. والحذف لا يُسترجع، فيتوقف الأمر إن
 * شارك القرص الخاص القرص العام موضعه، أو بدا الحذف جماعياً (قاعدة فارغة أو مستعادة من نسخة أقدم).
 */
class PrunePublicFiles extends Command
{
    /**
     * النماذج التي تطبّق HasPublicImages. ويختبر PrunePublicFilesTest ألا يغيب عنها نموذج.
     *
     * @var list<class-string<Model&HasPublicImages>>
     */
    public const MODELS = [Album::class, Announcement::class, BoardMember::class, Initiative::class, LibraryItem::class, NewsArticle::class, Page::class, Partner::class, Program::class];

    /**
     * فوق هذا العدد، وفوق نصف ملفات المجلدات، يُعد الحذف جماعياً فلا يتم إلا بـ --force.
     */
    public const MASS_DELETE_FLOOR = 50;

    protected $signature = 'ejaz:prune-public-files
        {--dry-run : عدّ ما سيُحذف دون حذفه}
        {--force : الحذف ولو بدا جماعياً}';

    protected $description = 'حذف صور القرص العام التي لا يشير إليها سجل بعد مدة';

    public function handle(): int
    {
        if ($this->sharesPrivateDisk()) {
            $this->error('القرص الخاص يشارك القرص العام موضعه، فلا يُحذف شيء (FILESYSTEM_PRIVATE_DISK).');

            return self::FAILURE;
        }

        $disk = Storage::disk('public');
        $before = now()->subDays(max(1, (int) config('filesystems.public_orphan_days')))->getTimestamp();
        $referenced = array_flip($this->referencedPaths());
        $total = 0;
        $orphans = [];

        foreach ($this->directories() as $directory) {
            foreach ($disk->allFiles($directory) as $path) {
                if (str_starts_with($path, MediaAsset::ORIGINALS_DIRECTORY.'/') || str_starts_with(basename($path), '.')) {
                    continue;
                }

                $total++;

                if (! isset($referenced[$path]) && $disk->lastModified($path) < $before) {
                    $orphans[] = $path;
                }
            }
        }

        if ($this->option('dry-run')) {
            $this->info('سيُحذف '.count($orphans).' ملف غير مرتبط.');
            $this->line(implode(PHP_EOL, $orphans));

            return self::SUCCESS;
        }

        if (count($orphans) > self::MASS_DELETE_FLOOR && count($orphans) > $total / 2 && ! $this->option('force')) {
            $this->error('سيُحذف '.count($orphans).' من '.$total.' ملف، فتوقف الأمر. راجع القاعدة، ثم أعده بـ --force إن كان مقصوداً.');
            Log::warning('ejaz:prune-public-files stopped: mass delete', ['orphans' => count($orphans), 'total' => $total]);

            return self::FAILURE;
        }

        $deleted = array_values(array_filter($orphans, fn (string $path): bool => $disk->delete($path)));

        $this->info('حُذف '.count($deleted).' ملف غير مرتبط.');
        Log::info('ejaz:prune-public-files', ['deleted' => $deleted, 'failed' => count($orphans) - count($deleted)]);

        return count($deleted) === count($orphans) ? self::SUCCESS : self::FAILURE;
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
     * كل مسار يشير إليه سجل: صور السجلات (والمحذوفة ناعماً، لأنها تُستعاد)، وصورة الرئيسية من القاعدة لا من
     * الذاكرة المؤقتة، والنسخ المعروضة في مكتبة الوسائط.
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

        $home = Setting::query()->where('key', 'home')->value('value');
        $hero = is_array($home) ? ($home['hero_image'] ?? null) : null;

        if (is_string($hero) && $hero !== '') {
            $paths[] = $hero;
        }

        // وصور «محتوى الرئيسية» (HomeContent) في مجلد الإعدادات نفسه
        $sections = Setting::query()->where('key', HomeContent::KEY)->value('value');
        array_push($paths, ...HomeContent::imagePaths(is_array($sections) ? $sections : []));

        return [...$paths, ...MediaAsset::query()->whereNotNull('web_path')->pluck('web_path')->all()];
    }

    /**
     * مجلدا المكتبة والوسائط باسم واحد على القرصين، فلو اشتركا في الموضع لحُذفت ملفات خاصة.
     */
    private function sharesPrivateDisk(): bool
    {
        $private = (string) config('filesystems.private_disk');
        $location = fn (string $disk): array => Arr::only((array) config('filesystems.disks.'.$disk), ['driver', 'root', 'bucket', 'endpoint']);

        return $private === 'public' || $location($private) === $location('public');
    }
}
