<?php

namespace App\Filament\Widgets;

/**
 * الجمعية العمومية (أعضاؤها الفعّالون واجتماعاتها)، والنماذج المعبأة بحالتيها، والأرشيف.
 */
class AssemblyStatsWidget extends PortalStatsWidget
{
    protected static ?int $sort = 8;

    protected function group(): string
    {
        return 'assembly';
    }

    protected function keys(): array
    {
        return ['members', 'upcoming_meetings', 'held_meetings', 'form_drafts', 'form_approved', 'archive_items'];
    }
}
