<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ما أُنفق على المزود في يوم (بتوقيت الرياض): التكلفة التقديرية وعدد الطلبات. يقرؤه السقف الشهري واليومي
 * (CheckAssistantBudget)، ولا يُحذف بمدة الاحتفاظ بالسجل، ولا شيء فيه عن الزوار.
 *
 * @property Carbon $day
 * @property string $cost
 * @property int $requests
 */
#[Fillable(['day', 'cost', 'requests'])]
class AssistantUsage extends Model
{
    protected $table = 'assistant_usage';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['day' => 'date', 'cost' => 'decimal:6', 'requests' => 'integer'];
    }

    public static function today(): string
    {
        return now((string) config('app.display_timezone'))->toDateString();
    }

    /**
     * يضيف تكلفة طلب إلى يومه بزيادة ذرّية واحدة، فلا يضيع طلب متزامن.
     */
    public static function add(float $cost): void
    {
        $now = now();

        DB::table('assistant_usage')->upsert(
            [['day' => self::today(), 'cost' => $cost, 'requests' => 1, 'created_at' => $now, 'updated_at' => $now]],
            ['day'],
            [
                'cost' => DB::raw('assistant_usage.cost + excluded.cost'),
                'requests' => DB::raw('assistant_usage.requests + 1'),
                'updated_at' => $now,
            ],
        );
    }

    public static function monthCost(): float
    {
        $start = now((string) config('app.display_timezone'))->startOfMonth()->toDateString();

        return (float) static::query()->where('day', '>=', $start)->sum('cost');
    }

    public static function dayCost(): float
    {
        return (float) static::query()->where('day', self::today())->sum('cost');
    }
}
