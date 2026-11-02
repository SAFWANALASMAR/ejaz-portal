<?php

namespace App\Support\ProgramStats;

use App\Contracts\ProgramStatsSource;
use App\Enums\RegistrationStatus;
use App\Models\ProgramRegistration;
use App\Models\ProgramType;

/**
 * صف لكل نوع برامج من اللوحة بترتيبه («الدورات التدريبية» و«المجالس العلمية» وما يُضاف)، ولو بلا برنامج.
 * - المسجلون: التسجيلات المؤكدة في برامج غير محذوفة، كـ«التسجيلات» في PortalStats. فلا الملغاة ولا قائمة الانتظار، ومن سجّل
 *   في برنامجين من النوع يُعدّ تسجيلين.
 * - المكملون: المؤكدة التي سُجّل حضورها (attended_at)، وهو ما تُبنى عليه شهادة الحضور (SyncAttendanceCertificate).
 * والصفحات والدقائق لا تُقاس لهذه البرامج (null).
 */
final class ProgramTypesSource implements ProgramStatsSource
{
    public function rows(): array
    {
        $counts = ProgramRegistration::query()
            ->join('programs', 'programs.id', '=', 'program_registrations.program_id')
            ->whereNull('programs.deleted_at')
            ->where('program_registrations.status', RegistrationStatus::Confirmed->value)
            ->toBase()
            ->selectRaw('programs.program_type_id as type, count(*) as registered, count(program_registrations.attended_at) as completed')
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
