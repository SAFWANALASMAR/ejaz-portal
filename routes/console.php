<?php

use App\Console\Commands\CheckHealth;
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

// صور القرص العام التي لا يشير إليها سجل بعد مدة (docs/plans/prune-public-files.md)
Schedule::command('ejaz:prune-public-files')->daily()->onOneServer();

// بيانات الحسابات المحذوفة بعد مدة الاحتفاظ من اللوحة، لكل الوحدات، وبلا مدة مضبوطة لا يُحذف شيء (docs/decisions.md)
Schedule::command('ejaz:purge-anonymized-data')->daily()->withoutOverlapping()->onOneServer();

// حسابات التسجيل الذاتي التي لم توثَّق قط ولم يعد إليها أصحابها بعد 14 يوماً، ما لم ترتبط بسجل أو بدور غير المستفيد (decisions.md — 14 أكتوبر 2026)
Schedule::command('ejaz:prune-unverified-accounts')->daily()->withoutOverlapping()->onOneServer();

// إحصائيات البوابة تُحسب في الخلفية وتُخزَّن (performance.md)
Schedule::command('ejaz:refresh-stats')->everyTenMinutes()->onOneServer();

// المعلّق القديم من عمليات الدفع: يُستعلم عن فاتورته أو يُعلَّم فاشلاً (docs/plans/store-payments.md)
Schedule::command('ejaz:sync-pending-payments')->hourly()->withoutOverlapping()->onOneServer();

// نبض المجدول كل دقيقة، يقرؤه فحص الصحة ejaz:health فينبّه إن توقفت مهمة cron (docs/monitoring.md)
Schedule::call(fn () => Cache::put(CheckHealth::HEARTBEAT_KEY, now()->getTimestamp(), now()->addDay()))
    ->everyMinute()
    ->name('schedule-heartbeat');

// تنبيه المسؤولين بالمستندات القريبة من الانتهاء والمنتهية، مرة لكل عتبة (docs/plans/registers.md)
Schedule::command('registers:send-alerts')->dailyAt('07:00')->timezone(config('app.display_timezone'))->withoutOverlapping()->onOneServer();
