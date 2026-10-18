<?php

namespace App\Filament\Widgets;

/**
 * البرامج والتسجيلات فيها، ثم المسابقات وقياس الرضا.
 */
class ProgramsStatsWidget extends PortalStatsWidget
{
    protected static ?int $sort = 7;

    protected function group(): string
    {
        return 'programs';
    }

    protected function keys(): array
    {
        return ['programs', 'registrations', 'waitlisted', 'attended', 'competitions', 'submissions', 'drafts', 'surveys', 'responses'];
    }
}
