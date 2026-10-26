<?php

namespace Database\Seeders;

use App\Enums\CompetitionFieldType;
use App\Enums\RankingScope;
use App\Models\Competition;
use App\Models\CompetitionCategory;
use App\Models\CompetitionField;
use App\Models\CompetitionRound;
use App\Support\CompetitionShortLink;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * مسابقة «البراهين العلمية» — الموسم الأول، من مطوية الجمعية (database/data/brahin-competition.php): مسودة غير منشورة
 * بنصوص الصفحة ودليل الدخول حرفياً، والفروع الأربعة مراحلَ بأيام خميس متتالية، وجدول الجوائز لكل جنس، والفئات
 * الأربع، وحقل «الجهة التعليمية / المدرسة» الاختياري، والرابط المختصر. والمواعيد مبدئية تُضبط من اللوحة قبل النشر
 * (أول خميس بعد أسبوع)، وبنك الأسئلة من المدير التنفيذي. ولا يمس مسابقة موجودة بالرابط نفسه (تعديل المسؤول باقٍ).
 *
 * عند النشر: php artisan db:seed --class=BrahinCompetitionSeeder --force
 */
class BrahinCompetitionSeeder extends Seeder
{
    public const DATA = 'data/brahin-competition.php';

    public function run(): void
    {
        /** @var array{slug: string, title: string, page_content: array<string, mixed>, prize_table: list<array{male: int, female: int}>, rounds: list<array{name: string, branch: string}>, fields: list<array{label: string, type: string, is_required: bool}>} $data */
        $data = require database_path(self::DATA);

        if (Competition::query()->withTrashed()->where('slug', $data['slug'])->exists()) {
            return;
        }

        (new CompetitionListsSeeder)->run();

        DB::transaction(function () use ($data): void {
            $zone = (string) config('app.display_timezone');
            $first = Carbon::now($zone)->addWeek()->startOfDay();

            while (! $first->isThursday()) {
                $first->addDay();
            }

            $last = $first->copy()->addWeeks(count($data['rounds']) - 1)->addDay();

            $competition = new Competition([
                'slug' => $data['slug'],
                'title' => $data['title'],
                'opens_at' => Carbon::now($zone)->startOfDay()->utc(),
                'closes_at' => $last->copy()->utc(),
                'is_published' => false,
                'student_mode' => true,
                'categories' => [],
                'page_content' => $data['page_content'],
                'prize_table' => $data['prize_table'],
                'ranking_scope' => RankingScope::All,
            ]);
            $competition->save();
            $competition->openCategories()->sync(CompetitionCategory::query()->pluck('id')->all());

            foreach ($data['rounds'] as $index => $row) {
                $starts = $first->copy()->addWeeks($index);

                $round = new CompetitionRound([
                    'name' => $row['name'],
                    'branch' => $row['branch'],
                    'sort_order' => $index + 1,
                    'starts_at' => $starts->copy()->utc(),
                    'ends_at' => $starts->copy()->addDay()->utc(),
                    'question_count' => (int) config('competitions.student.default_questions'),
                    'duration_minutes' => (int) config('competitions.student.default_minutes'),
                ]);
                $round->competition_id = $competition->id;
                $round->save();
            }

            foreach ($data['fields'] as $index => $row) {
                $field = new CompetitionField([
                    'label' => $row['label'],
                    'type' => CompetitionFieldType::from($row['type']),
                    'is_required' => $row['is_required'],
                    'sort_order' => $index + 1,
                ]);
                $field->competition_id = $competition->id;
                $field->save();
            }

            CompetitionShortLink::assign($competition);
        });
    }
}
