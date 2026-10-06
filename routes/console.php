<?php

use Illuminate\Support\Facades\Schedule;

/*
| المهام المجدولة. تعمل بـ php artisan schedule:run كل دقيقة في الإنتاج
| (أو schedule:work في التطوير) — docs/workflow.md
*/

// سجل العمليات يُحذف ما تجاوز مدة الاحتفاظ منه، ومنه عناوين IP في السجل الأمني (docs/decisions.md)
Schedule::command('activitylog:clean --force')->daily()->onOneServer();
