<?php

namespace App\Console\Commands;

use App\Actions\Content\ImportLegacyContent;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * نقل محتوى الموقع الحالي (content-inventory.md — طريقة النقل): يقرأ ملفاً مُعداً ولا يكشط الموقع،
 * وكل ما ينشئه مسودة يراجعها صفوان أو الجمعية قبل النشر. والمنطق كله في ImportLegacyContent.
 */
class ImportLegacySite extends Command
{
    protected $signature = 'import:legacy-site
        {file : ملف JSON المُعد من الموقع الحالي، والملفات بمسارات نسبية إلى مجلده}
        {--actor= : بريد المشرف الذي يُنسب إليه الاستيراد في سجل العمليات}
        {--dry-run : يعرض ما سيحدث بفحوصه كلها، ولا يبقي شيئاً}';

    protected $description = 'استيراد محتوى الموقع الحالي مسوداتٍ غير منشورة، مع تحويل روابطه القديمة';

    public function handle(ImportLegacyContent $import): int
    {
        $email = mb_strtolower(trim((string) $this->option('actor')));
        $actor = $email === '' ? null : User::query()->where('email', $email)->first();

        if ($actor === null || ! $actor->is_active || $actor->anonymized_at !== null) {
            $this->error(__('import.errors.actor'));

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        try {
            $report = $import->handle($actor, (string) $this->argument('file'), $dryRun);
        } catch (ValidationException $exception) {
            $this->error((string) collect($exception->errors())->flatten()->first());

            return self::FAILURE;
        } catch (AuthorizationException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($report as $row) {
            $line = '['.__('import.sections.'.$row['section']).'] '.$row['key'].' — '.$row['detail'];
            $row['outcome'] === 'rejected' ? $this->error($line) : $this->line($line);
        }

        $count = fn (string ...$outcomes): int => collect($report)->whereIn('outcome', $outcomes)->count();

        $this->info(__('import.summary', [
            'created' => $count('created'),
            'filled' => $count('filled'),
            'skipped' => $count('unchanged', 'published', 'deleted'),
            'rejected' => $count('rejected'),
            'redirects' => $count('redirect_created'),
        ]));

        if ($dryRun) {
            $this->warn(__('import.dry_run_done'));
        }

        return $count('rejected') > 0 ? self::FAILURE : self::SUCCESS;
    }
}
