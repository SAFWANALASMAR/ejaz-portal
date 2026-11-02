<?php

use App\Support\ProgramStats\CompetitionsSource;
use App\Support\ProgramStats\LibraryProgramsSource;
use App\Support\ProgramStats\ProgramTypesSource;

/*
| مصادر «إحصائيات البرامج لكل نوع» (البند R23 — docs/decisions/2026-11-02-program-stats-by-type.md) بترتيب عرضها. كل مصدر
| صنف يطبّق App\Contracts\ProgramStatsSource، ويُضاف نوع جديد بسطر واحد هنا. ومصدر لاحق بمفتاح صف سابق يحل محله.
*/
return [

    'sources' => [
        ProgramTypesSource::class,
        CompetitionsSource::class,
        LibraryProgramsSource::class,
    ],

];
