<?php

namespace App\Policies;

/**
 * مقالات «دليل استخدام البوابة»: كل العمليات بصلاحية ai_assistant.manage (المسؤول التقني)، والاطلاع للمشرف العام.
 */
class AssistantGuidePolicy extends AssistantFaqPolicy {}
