<?php

namespace App\Enums;

/**
 * مراحل رحلة الاختبار الإلكتروني بترتيبها. تُحسب من أوقات المشاركة ولا تُخزَّن
 * (CompetitionSubmission::examStage)، ولا يُنتقل إلى مرحلة قبل إكمال سابقتها.
 */
enum ExamStage: string
{
    case Registration = 'registration';
    case Intro = 'intro';
    case Instructions = 'instructions';
    case Questions = 'questions';
    case Submitted = 'submitted';
}
