<?php

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

// بيانات الحسابات المحذوفة بعد مدة الاحتفاظ من اللوحة، لكل الوحدات، وبلا مدة مضبوطة لا يُحذف شيء (docs/decisions.md)
Schedule::command('ejaz:purge-anonymized-data')->daily()->withoutOverlapping()->onOneServer();

// إحصائيات البوابة تُحسب في الخلفية وتُخزَّن (performance.md)
Schedule::command('ejaz:refresh-stats')->everyTenMinutes()->onOneServer();

// المعلّق القديم من عمليات الدفع: يُستعلم عن فاتورته أو يُعلَّم فاشلاً (docs/plans/store-payments.md)
Schedule::command('ejaz:sync-pending-payments')->hourly()->withoutOverlapping()->onOneServer();
