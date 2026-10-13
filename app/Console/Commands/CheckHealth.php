<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * فحص صحة أعمق من /up للمراقبة على الخادم (docs/monitoring.md): القاعدة، والذاكرة المؤقتة،
 * وتراكم الطابور، والمهام الفاشلة، ومساحة القرص، ونبض المجدول. يخرج برمز غير صفري إن فشل فحص،
 * فتنبّه أداة المراقبة.
 */
class CheckHealth extends Command
{
    /** يكتبه المجدول كل دقيقة (routes/console.php) */
    public const HEARTBEAT_KEY = 'ejaz:schedule-heartbeat';

    protected $signature = 'ejaz:health';

    protected $description = 'فحص صحة البوابة: القاعدة والذاكرة والطابور والمهام الفاشلة والقرص والتخزين الخاص والمجدول';

    public function handle(): int
    {
        $checks = [
            'database' => fn (): ?string => $this->database(),
            'cache' => fn (): ?string => $this->cache(),
            'queue' => fn (): ?string => $this->queue(),
            'video_queue' => fn (): ?string => $this->videoQueue(),
            'failed_jobs' => fn (): ?string => $this->failedJobs(),
            'disk' => fn (): ?string => $this->disk(),
            'private_storage' => fn (): ?string => $this->privateStorage(),
            'schedule' => fn (): ?string => $this->schedule(),
        ];

        $failures = 0;

        foreach ($checks as $name => $check) {
            try {
                $problem = $check();
            } catch (Throwable $e) {
                // نوع الخطأ وحده، فرسالته قد تحمل عنوان الخادم أو بيانات الاتصال
                $problem = class_basename($e);
            }

            if ($problem === null) {
                $this->line('✓ '.__('health.checks.'.$name).': '.__('health.ok'));
            } else {
                $failures++;
                $this->error('✗ '.__('health.checks.'.$name).': '.$problem);
            }
        }

        if ($failures > 0) {
            $this->error(__('health.summary_failed', ['count' => $failures]));

            return self::FAILURE;
        }

        $this->info(__('health.summary_ok'));

        return self::SUCCESS;
    }

    private function database(): null
    {
        DB::select('select 1');

        return null;
    }

    private function cache(): ?string
    {
        $key = 'ejaz:health:'.Str::random(8);

        Cache::put($key, 'ok', 10);
        $read = Cache::get($key);
        Cache::forget($key);

        return $read === 'ok' ? null : __('health.failed.cache');
    }

    private function queue(): ?string
    {
        $size = Queue::size();
        $max = (int) config('health.queue_max_backlog');

        return $size > $max ? __('health.failed.queue', ['size' => $size, 'max' => $max]) : null;
    }

    /**
     * طابور مقاطع المسابقات المستقل (supervisor-videos): تراكمه يعني عمالاً متوقفين، فلا تُعالج المقاطع.
     */
    private function videoQueue(): ?string
    {
        $size = Queue::size('videos');
        $max = (int) config('health.video_queue_max_backlog');

        return $size > $max ? __('health.failed.queue', ['size' => $size, 'max' => $max]) : null;
    }

    private function failedJobs(): ?string
    {
        $count = DB::table((string) config('queue.failed.table', 'failed_jobs'))
            ->where('failed_at', '>=', now()->subDay())
            ->count();
        $max = (int) config('health.failed_jobs_max');

        return $count > $max ? __('health.failed.failed_jobs', ['count' => $count, 'max' => $max]) : null;
    }

    private function disk(): ?string
    {
        $total = disk_total_space(storage_path());
        $free = disk_free_space(storage_path());

        if ($total === false || $free === false || $total <= 0) {
            return __('health.failed.disk', ['free' => '?', 'min' => config('health.disk_min_free_percent')]);
        }

        $percent = (int) floor($free / $total * 100);
        $min = (int) config('health.disk_min_free_percent');

        return $percent < $min ? __('health.failed.disk', ['free' => $percent, 'min' => $min]) : null;
    }

    /**
     * القرص الخاص (S3 في الإنتاج): ملف صغير يُكتب ويُقرأ ويُحذف، فيظهر خطأ الاتصال أو المفاتيح أو الصلاحيات
     * قبل أن يظهر عند رفع زائر.
     */
    private function privateStorage(): ?string
    {
        $disk = Storage::disk((string) config('filesystems.private_disk', 'local'));
        $path = 'health/'.Str::random(16).'.txt';

        try {
            $disk->put($path, 'ok');
            $read = $disk->get($path);
        } finally {
            $disk->delete($path);
        }

        return $read === 'ok' ? null : __('health.failed.private_storage');
    }

    private function schedule(): ?string
    {
        $beat = Cache::get(self::HEARTBEAT_KEY);

        // Redis يعيد الرقم المخزَّن نصاً، فالنبض رقم بأي من الصيغتين
        if (! is_int($beat) && ! (is_string($beat) && ctype_digit($beat))) {
            return __('health.failed.schedule_missing');
        }

        $minutes = intdiv(now()->getTimestamp() - (int) $beat, 60);

        return $minutes > (int) config('health.schedule_max_silence_minutes')
            ? __('health.failed.schedule_stale', ['minutes' => $minutes])
            : null;
    }
}
