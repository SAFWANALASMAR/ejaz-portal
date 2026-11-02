<?php

namespace App\Contracts;

/**
 * مصدر لإحصائيات نوع أو أكثر من البرامج (البند R23 — docs/decisions/2026-11-02-program-stats-by-type.md). يُسجَّل في
 * config/program-stats.php بسطر واحد، ويحسبه ProgramStatsByType في الخلفية مع ejaz:refresh-stats.
 *
 * كل صف مجاميع وحدها بلا بيانات شخصية. و«الصفحات» و«الدقائق» null للنوع الذي لا يقيسهما (فلا تظهر خانتهما)، وصفر لمن
 * يقيسهما ولا شيء فيهما بعد. ومفتاح الصف ثابت: معرّف نوع البرنامج (slug) أو اسم المصدر، ومصدر لاحق في القائمة بمفتاح
 * سابق يحل محل صفه.
 */
interface ProgramStatsSource
{
    /**
     * @return list<array{key: string, label: string, registered: int, completed: int, pages: int|null, minutes: int|null}>
     */
    public function rows(): array;
}
