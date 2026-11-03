<?php

namespace App\Support\ProgramStats;

use App\Contracts\ProgramStatsSource;
use App\Enums\RegistrationStatus;
use App\Models\ProgramRegistration;
use App\Models\ProgramType;
use Illuminate\Support\Facades\Schema;

/**
 * صف لكل نوع برامج من اللوحة بترتيبه («الدورات التدريبية» و«المجالس العلمية» وما يُضاف)، ولو بلا برنامج.
 * - المسجلون: التسجيلات المؤكدة في برامج غير محذوفة، كـ«التسجيلات» في PortalStats. فلا الملغاة ولا قائمة الانتظار، ومن سجّل
 *   في برنامجين من النوع يُعدّ تسجيلين.
 * - المكملون: المؤكدة التي سُجّل حضورها (attended_at، وعليه تُبنى شهادة الحضور) أو أتمّها صاحبها (completed_at في برامج
 *   المكتبة من #126)، كتعريف ProgramTypeStats هناك.
 * والصفحات والدقائق لا تُقاس لهذه البرامج (null).
 */
final class ProgramTypesSource implements ProgramStatsSource
{
    public function rows(): array
    {
        // عمود الإتمام يأتي مع برامج المكتبة (#126). والفحص في الخلفية مرة لكل حساب، لا عند الطلب
        $completed = Schema::hasColumn('program_registrations', 'completed_at')
            ? 'program_registrations.attended_at is not null or program_registrations.completed_at is not null'
            : 'program_registrations.attended_at is not null';

        $counts = ProgramRegistration::query()
            ->join('programs', 'programs.id', '=', 'program_registrations.program_id')
            ->whereNull('programs.deleted_at')
            ->where('program_registrations.status', RegistrationStatus::Confirmed->value)
            ->toBase()
            ->selectRaw('programs.program_type_id as type, count(*) as registered, count(*) filter (where '.$completed.') as completed')
            ->groupBy('programs.program_type_id')
            ->get()
            ->keyBy('type');

        return ProgramType::query()->ordered()->get(['id', 'slug', 'name'])
            ->map(fn (ProgramType $type): array => [
                'key' => $type->slug,
                'label' => $type->name,
                'registered' => (int) ($counts[$type->id]->registered ?? 0),
                'completed' => (int) ($counts[$type->id]->completed ?? 0),
                'pages' => null,
                'minutes' => null,
            ])
            ->values()
            ->all();
    }
}
