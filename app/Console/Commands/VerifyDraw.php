<?php

namespace App\Console\Commands;

use App\Actions\Competitions\RunTieDraw;
use App\Enums\SubmissionStatus;
use App\Models\CompetitionDraw;
use App\Support\CumulativeRanking;
use App\Support\DrawSelector;
use Illuminate\Console\Command;

/**
 * التحقق من سحب أمام لجنة المسابقة: بصمة القائمة المسجلة، والفائزون المعادون من البذرة، ومطابقة
 * القائمة للمشاركات المرسلة الآن.
 */
class VerifyDraw extends Command
{
    protected $signature = 'ejaz:verify-draw {uuid : معرّف السحب}';

    protected $description = 'إعادة سحب إلكتروني من بذرته للتحقق';

    public function handle(): int
    {
        $draw = CompetitionDraw::query()->where('uuid', (string) $this->argument('uuid'))->first();

        if ($draw === null) {
            $this->error('لا سحب بهذا المعرّف.');

            return self::FAILURE;
        }

        $hashMatches = DrawSelector::fingerprint($draw->entrants) === $draw->entrants_hash;
        $winnersMatch = DrawSelector::winners($draw->seed, $draw->entrants, $draw->winners_count) === $draw->winners;
        // سحب المتساوين في مرحلة (RunTieDraw): القائمة الآن هي المتساوون على درجة التساوي نفسها
        // وسحب الترتيب التراكمي (RunCumulativeTieDraw): المتساوون في المجموع على درجة التساوي نفسها
        $current = match (true) {
            $draw->round_id !== null && $draw->round !== null => app(RunTieDraw::class)->ranked($draw->round, $draw->category_id)
                ->filter(fn (array $row): bool => $row['total'] === (float) $draw->tie_score)->pluck('reference')->sort()->values()->all(),
            $draw->gender !== null => CumulativeRanking::ranked($draw->competition, $draw->gender, $draw->category_id)
                ->filter(fn (array $row): bool => $row['total'] === (float) $draw->tie_score)->pluck('reference')->sort()->values()->all(),
            default => $draw->competition->submissions()->where('status', SubmissionStatus::Submitted)->whereNotNull('reference')->orderBy('reference')->pluck('reference')->all(),
        };

        $this->line('بصمة المشمولين: '.($hashMatches ? 'مطابقة' : 'غير مطابقة'));
        $this->line('الفائزون من البذرة: '.($winnersMatch ? 'مطابقون' : 'غير مطابقين'));
        $this->line('المشاركات المرسلة الآن: '.(DrawSelector::fingerprint($current) === $draw->entrants_hash ? 'كما كانت عند السحب' : 'تغيّرت بعد السحب'));
        $this->line('الفائزون بالترتيب: '.implode('، ', $draw->winners));

        return $hashMatches && $winnersMatch ? self::SUCCESS : self::FAILURE;
    }
}
