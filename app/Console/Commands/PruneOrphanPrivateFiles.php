<?php

namespace App\Console\Commands;

use App\Models\FormEntry;
use App\Models\ServiceRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * يحذف من القرص الخاص الملفات الشخصية التي لا يشير إليها سجل: مرفقات النماذج المعبأة وتواقيعها، ومرفقات
 * الطلبات (مراجعة التكامل، 14 أكتوبر 2026). استعادة الملفات من نسخة احتياطية تعيد ما حذفه الحذف بعد
 * مدة الاحتفاظ أو حذفه صاحبه، ولا سجل يشير إليه فلا يحذفه شيء غير هذا الأمر (scripts/restore.md).
 *
 * والحذف محصور في المجلدات الثلاثة، والمدة تحمي ما رُفع الآن ولم يُحفظ سجله بعد. والسجلات المحذوفة ناعماً
 * تُعد مرجعاً لأنها تُستعاد. والحذف لا يُسترجع، فيتوقف الأمر إن بدا جماعياً إلا بـ --force.
 */
class PruneOrphanPrivateFiles extends Command
{
    protected $signature = 'ejaz:prune-orphan-private-files
        {--dry-run : عدّ ما سيُحذف دون حذفه}
        {--days= : أقل عمر للملف بالأيام قبل حذفه (0 بعد الاستعادة والموقع متوقف)}
        {--force : الحذف ولو بدا جماعياً}';

    protected $description = 'حذف الملفات الشخصية في القرص الخاص التي لا يشير إليها سجل';

    /**
     * @return list<string>
     */
    public static function directories(): array
    {
        return [FormEntry::FILES_DIRECTORY, FormEntry::SIGNATURES_DIRECTORY, ServiceRequest::FILE_DIRECTORY];
    }

    public function handle(): int
    {
        $days = $this->option('days');
        $days = is_numeric($days) ? max(0, (int) $days) : max(1, (int) config('filesystems.private_orphan_days'));
        // صفر: بلا شرط عمر (بعد الاستعادة والموقع متوقف)
        $before = $days > 0 ? now()->subDays($days)->getTimestamp() : null;

        $disk = Storage::disk((string) config('filesystems.private_disk', 'local'));
        $referenced = array_flip($this->referencedPaths());
        $total = 0;
        $orphans = [];

        foreach (self::directories() as $directory) {
            foreach ($disk->allFiles($directory) as $path) {
                if (str_starts_with(basename($path), '.')) {
                    continue;
                }

                $total++;

                if (! isset($referenced[$path]) && ($before === null || $disk->lastModified($path) <= $before)) {
                    $orphans[] = $path;
                }
            }
        }

        if ($this->option('dry-run')) {
            $this->info('سيُحذف '.count($orphans).' ملف غير مرتبط من '.$total.'.');
            $this->line(implode(PHP_EOL, $orphans));

            return self::SUCCESS;
        }

        if (count($orphans) > PrunePublicFiles::MASS_DELETE_FLOOR && count($orphans) > $total / 2 && ! $this->option('force')) {
            $this->error('سيُحذف '.count($orphans).' من '.$total.' ملف، فتوقف الأمر. راجع القاعدة، ثم أعده بـ --force إن كان مقصوداً.');
            Log::warning('ejaz:prune-orphan-private-files stopped: mass delete', ['orphans' => count($orphans), 'total' => $total]);

            return self::FAILURE;
        }

        $deleted = array_values(array_filter($orphans, fn (string $path): bool => $disk->delete($path)));

        $this->info('حُذف '.count($deleted).' ملف غير مرتبط.');
        // العدد وحده: مسارات ملفات شخصية لا حاجة إليها في السجل
        Log::info('ejaz:prune-orphan-private-files', ['deleted' => count($deleted), 'failed' => count($orphans) - count($deleted)]);

        return count($deleted) === count($orphans) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * كل مسار يشير إليه سجل، والمحذوف ناعماً معه.
     *
     * @return list<string>
     */
    private function referencedPaths(): array
    {
        $paths = [];

        foreach (FormEntry::withTrashed()->select(['id', 'files', 'signatures'])->lazyById(500) as $entry) {
            array_push($paths, ...array_column((array) $entry->files, 'path'), ...array_column((array) $entry->signatures, 'path'));
        }

        foreach (ServiceRequest::withTrashed()->select(['id', 'files'])->lazyById(500) as $request) {
            array_push($paths, ...array_column(array_values((array) $request->files), 'path'));
        }

        return $paths;
    }
}
