<?php

namespace App\Console\Commands;

use App\Actions\Competitions\RegisterStudent;
use App\Models\Competition;
use App\Models\CompetitionRegion;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * تسجيل حسابات اختبار التحمل (ejaz:load-accounts) في مسابقة طلاب، بأرقام هوية صحيحة الصيغة متسلسلة، عبر
 * إجراء التسجيل نفسه (tests/load/README.md — «مسابقة الطلاب»). للبيئة المحلية والتجريبية فقط.
 */
#[Signature('ejaz:load-students {competition : رابط المسابقة} {count : عدد الطلاب}')]
#[Description('تسجيل حسابات اختبار التحمل في مسابقة طلاب (المحلية والتجريبية فقط)')]
class RegisterLoadStudents extends Command
{
    public function handle(RegisterStudent $register): int
    {
        if (! $this->laravel->environment('local', 'testing', 'staging')) {
            $this->error('هذا الأمر للبيئة المحلية والتجريبية فقط.');

            return self::FAILURE;
        }

        $competition = Competition::query()->where('slug', (string) $this->argument('competition'))->where('student_mode', true)->first();

        if ($competition === null) {
            $this->error('لا مسابقة طلاب بهذا الرابط.');

            return self::FAILURE;
        }

        $category = $competition->openCategories()->get()->first();
        $region = CompetitionRegion::query()->active()->value('id');
        $count = (int) $this->argument('count');
        $registered = 0;

        if ($category === null || $region === null) {
            $this->error('المسابقة بلا فئة مفتوحة، أو لا منطقة مفعّلة.');

            return self::FAILURE;
        }

        for ($n = 1; $n <= $count; $n++) {
            $user = User::query()->where('email', 'load-'.$n.'@'.CreateLoadAccounts::DOMAIN)->first();

            if ($user === null || $competition->submissions()->where('user_id', $user->id)->exists()) {
                continue;
            }

            $register->handle($user, $competition, [
                'full_name' => 'طالب اختبار التحمل بن مثال',
                'national_id' => self::nationalId($n),
                'category_id' => $category->id,
                'grade' => $category->gradeList()[0] ?? null,
                'region_id' => $region,
                'pledge' => '1',
            ]);
            $registered++;
        }

        $this->info((string) $registered);

        return self::SUCCESS;
    }

    /**
     * رقم بصيغة الهوية ورقم تحققها من رقم تسلسلي، في مدى يبدأ بـ 19 لا يُتوقع لأشخاص في البيئة التجريبية.
     */
    public static function nationalId(int $serial): string
    {
        $body = '19'.str_pad((string) $serial, 7, '0', STR_PAD_LEFT);
        $sum = 0;

        foreach (str_split($body) as $position => $digit) {
            $number = (int) $digit * ($position % 2 === 0 ? 2 : 1);
            $sum += $number > 9 ? $number - 9 : $number;
        }

        return $body.((10 - $sum % 10) % 10);
    }
}
