<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * مجموع زيارات يوم بتوقيت الرياض: المشاهدات، والزوار الفريدون. ينقله ejaz:refresh-stats من الذاكرة المؤقتة
 * (App\Support\Visits)، ولا يُكتب من غيره.
 *
 * @property Carbon $date
 * @property int $views
 * @property int $visitors
 */
#[Fillable(['date', 'views', 'visitors'])]
class DailyVisit extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'views' => 'integer',
            'visitors' => 'integer',
        ];
    }
}
