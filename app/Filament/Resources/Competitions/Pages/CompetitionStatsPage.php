<?php

namespace App\Filament\Resources\Competitions\Pages;

use App\Filament\Resources\Competitions\CompetitionResource;
use App\Models\Competition;
use App\Support\CompetitionStats;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

/**
 * لوحة متابعة المسابقة: أرقام بلا بيانات شخصية لمن يطّلع على المسابقة (ومنهم عضو المجلس)، تتحدث كل
 * دقيقة. والتصدير الكامل من جدول المشاركات لمن له submissions.export.
 */
class CompetitionStatsPage extends Page
{
    use InteractsWithRecord;

    protected static string $resource = CompetitionResource::class;

    protected string $view = 'filament.competitions.stats';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(auth()->user()?->can('view', $this->getRecord()), 403);
    }

    public function getTitle(): string
    {
        /** @var Competition $competition */
        $competition = $this->getRecord();

        return __('competitions.stats.title', ['competition' => $competition->title]);
    }

    /**
     * @return array{registered: int, drafts: int, submitted: int, categories: array<string, int>, regions: array<string, int>}
     */
    public function getStats(): array
    {
        /** @var Competition $competition */
        $competition = $this->getRecord();

        return CompetitionStats::for($competition);
    }
}
