<?php

namespace App\Console\Commands;

use App\Jobs\FetchHomeVideoThumbnail;
use App\Models\CompetitionRound;
use App\Models\Page;
use App\Support\ContentCache;
use App\Support\RoundQuestions;
use Illuminate\Console\Command;

/**
 * يمسح ذاكرة المحتوى العام وحدها بعد كل نشر، لأن القوالب والمسارات الجديدة تغيّر ما يُعرض
 * (بطاقة قسم بُني للتو مثلاً). بديل cache:clear الذي يصفّر حدود الحماية.
 *
 * ومعها معرّفات الصفحات المنشورة (مفتاحها مستقل عن ذاكرة المحتوى: Page::publishedSlugs)، فتظهر استعادة
 * القاعدة أو تعديلها المباشر في القائمة و/privacy فوراً، لا بعد ساعتها الاحتياطية.
 *
 * ومعها بنوك مراحل مسابقة الطلاب المخزنة وفحص الدرجة القصوى بين الفئات (RoundQuestions وAttemptLayout): ترحيل يغيّر
 * الفئات أو الأسئلة مباشرة (دمج فئة «المهتمون» مثلاً) لا يمر بإجراءات اللوحة التي تمسحها (مراجعة المشرف على #67).
 */
class RefreshContentCache extends Command
{
    protected $signature = 'ejaz:refresh-content';

    protected $description = 'مسح ذاكرة المحتوى العام بعد النشر، دون المساس بحدود الحماية';

    public function handle(): int
    {
        ContentCache::flush();
        Page::flushPublishedSlugs();
        CompetitionRound::query()->pluck('id')->each(fn (int $id) => RoundQuestions::forget($id));
        // صورة مقطع الرئيسية إن لم تُجلب (مقطع ضُبط قبل وجود الجلب)
        FetchHomeVideoThumbnail::queueForCurrent();

        $this->info('مُسحت ذاكرة المحتوى العام وبنوك مراحل المسابقات.');

        return self::SUCCESS;
    }
}
