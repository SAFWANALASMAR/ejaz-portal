<?php

namespace App\Policies;

/**
 * مقالات «دليل استخدام البوابة»: كل العمليات بصلاحية ai_assistant.manage (المشرف العام وحده).
 */
class AssistantGuidePolicy extends AssistantFaqPolicy {}
