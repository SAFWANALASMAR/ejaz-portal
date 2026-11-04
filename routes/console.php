<?php

use App\Console\Commands\CheckHealth;
use App\Models\AssistantLog;
use App\Models\EvidenceGalleryVisit;
use App\Models\MessageLog;
use App\Support\HomeShowcase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

/*
| المهام المجدولة. في التطوير تشغّلها خدمة scheduler في compose.yaml (schedule:work)،
| وفي الإنتاج مهمة cron تشغّل php artisan schedule:run كل دقيقة — docs/workflow.md
*/

// سجل العمليات يُحذف ما تجاوز مدة الاحتفاظ منه، ومنه عناوين IP في السجل الأمني (docs/decisions.md)
Schedule::command('activitylog:clean --force')->daily()->onOneServer();

// ملفات التصدير ببياناتها الشخصية لا تبقى أكثر من يوم: الطلبات والبرامج والمسابقات (docs/decisions.md)
Schedule::command('ejaz:prune-exports')->hourly()->onOneServer();

// تذكير المسجلين في البرامج قبل موعدها بيوم (requirements/03)
Schedule::command('programs:send-reminders')->hourly()->onOneServer();

// أجزاء مقاطع المسابقات المهجورة لا تبقى على القرص الخاص
Schedule::command('ejaz:prune-video-uploads')->daily()->onOneServer();

// طلبات إلغاء السحب التي لم يقرر فيها مشرف عام آخر خلال سبعة أيام تسقط، ويبقى السحب (docs/decisions.md)
Schedule::command('ejaz:lapse-draw-void-requests')->daily()->onOneServer();

// التسليم الآلي لمحاولات مسابقة الطلاب التي انتهى وقتها ولم يعد صاحبها (docs/plans/student-competition.md)
// وقفل منع التداخل خمس دقائق لا 24 ساعة (الافتراضي): إعادة تشغيل الحاويات أثناء التشغيل تترك القفل معلقاً، فيتوقف الإغلاق يوماً كاملاً
Schedule::command('ejaz:close-expired-attempts')->everyMinute()->withoutOverlapping(5)->onOneServer();

// شريحة البراهين في الرئيسية بحالتها: تُمسح الرئيسية المخزّنة حين يعبر الوقت موعد الفتح أو الإغلاق (ملاحظة صفوان ٣٠)
// والدالة ترد false حين لا موعد عابر، وLaravel يعدّ الرد false فشلاً فيسجل خطأ كل دقيقة، فالإغلاق لا يرد شيئاً
Schedule::call(function (): void {
    HomeShowcase::forgetHomeAtCompetitionBoundary(now()->subMinutes(2));
})
    ->everyMinute()
    ->name('home-competition-boundary')
    ->onOneServer();

// صور القرص العام التي لا يشير إليها سجل بعد مدة (docs/plans/prune-public-files.md)
Schedule::command('ejaz:prune-public-files')->daily()->onOneServer();

// الملفات الشخصية في القرص الخاص التي لا يشير إليها سجل، ومنها ما أعادته استعادة نسخة احتياطية (scripts/restore.md)
Schedule::command('ejaz:prune-orphan-private-files')->weekly()->withoutOverlapping()->onOneServer();

// بيانات الحسابات المحذوفة بعد مدة الاحتفاظ من اللوحة، لكل الوحدات، وبلا مدة مضبوطة لا يُحذف شيء (docs/decisions.md)
Schedule::command('ejaz:purge-anonymized-data')->daily()->withoutOverlapping()->onOneServer();

// حسابات التسجيل الذاتي التي لم توثَّق قط ولم يعد إليها أصحابها بعد 14 يوماً، ما لم ترتبط بسجل أو بدور غير المستفيد (decisions.md — 14 أكتوبر 2026)
Schedule::command('ejaz:prune-unverified-accounts')->daily()->withoutOverlapping()->onOneServer();

// إحصائيات البوابة تُحسب في الخلفية وتُخزَّن (performance.md)
Schedule::command('ejaz:refresh-stats')->everyTenMinutes()->onOneServer();

// المعلّق القديم من عمليات الدفع: يُستعلم عن فاتورته أو يُعلَّم فاشلاً (docs/plans/store-payments.md)
Schedule::command('ejaz:sync-pending-payments')->hourly()->withoutOverlapping()->onOneServer();

// تقرير تحليل الإحصائيات المعلّق بعد مهلته «تعذّر»، لا عند عرض الصفحة (مراجعة ما بعد v0.12.0)
Schedule::command('ejaz:fail-stale-analyses')->everyFiveMinutes()->onOneServer();

// نبض المجدول كل دقيقة، يقرؤه فحص الصحة ejaz:health فينبّه إن توقفت مهمة cron (docs/monitoring.md)
Schedule::call(fn () => Cache::put(CheckHealth::HEARTBEAT_KEY, now()->getTimestamp(), now()->addDay()))
    ->everyMinute()
    ->name('schedule-heartbeat');

// تنبيه المسؤولين بالمستندات القريبة من الانتهاء والمنتهية، مرة لكل عتبة (docs/plans/registers.md)
Schedule::command('registers:send-alerts')->dailyAt('07:00')->timezone(config('app.display_timezone'))->withoutOverlapping()->onOneServer();

// سجل محادثات المساعد يُحذف بعد مدة الاحتفاظ من إعداداته (requirements/10)، وفهرسه يُعاد بناؤه ليلياً احتياطاً لما
// تغيّر خارج اللوحة
Schedule::command('model:prune', ['--model' => [AssistantLog::class]])->daily()->onOneServer();
Schedule::command('ejaz:assistant-index')->dailyAt('03:30')->timezone(config('app.display_timezone'))->withoutOverlapping()->onOneServer();

// سجل حالة الرسائل بعد مدة الاحتفاظ (config/messaging.php: log_days)
Schedule::command('model:prune', ['--model' => [MessageLog::class]])->daily()->onOneServer();

// سجل فتح معارض الشواهد بعد مدة الاحتفاظ بسجل العمليات (activitylog.clean_after_days)
Schedule::command('model:prune', ['--model' => [EvidenceGalleryVisit::class]])->daily()->onOneServer();

// المهام الفاشلة بعد أسبوع، كما يحذفها Horizon من قائمته (config/horizon.php: trim.failed)، فلا يبقى نص استثناءاتها
// بلا حد (اختبار الأمن قبل الرفع، مراجعة #61)
Schedule::command('queue:prune-failed', ['--hours' => 168])->daily()->onOneServer();
