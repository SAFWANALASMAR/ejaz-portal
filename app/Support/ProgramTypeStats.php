<?php

namespace App\Support;

use App\Enums\LibraryProgramKind;
use App\Enums\RegistrationStatus;
use App\Models\ProgramType;
use Illuminate\Support\Facades\DB;

/**
 * مجاميع البرامج لكل نوع، للوحة الإحصائيات وبوابة المجلس (البند ٤ من مهام التحقق، 2 نوفمبر — يُبنى عرضها عليها): المسجلون
 * المؤكدون، والمتمّون (الحضور للبرامج الحضورية، والإتمام لبرامج المكتبة)، ونسبة الإتمام، والصفحات المقروءة ودقائق الاستماع
 * والمشاهدة في عناصر برامج المكتبة من سجل تقدّم المسجلين فيها. والمسابقات تُعدّ في سجل الإحصائيات بأنواعه (#127)، لا هنا.
 * أرقام مجمّعة بلا بيانات شخصية، باستعلام لكل مجموعة لا لكل برنامج.
 */
final class ProgramTypeStats
{
    /**
     * @return list<array{key: string, label: string, registered: int, completed: int, rate: float, pages: int, minutes: int}>
     */
    public static function compute(): array
    {
        $registrations = DB::table('program_registrations')
            ->join('programs', 'programs.id', '=', 'program_registrations.program_id')
            ->whereNull('programs.deleted_at')
            ->where('program_registrations.status', RegistrationStatus::Confirmed->value)
            ->groupBy('programs.program_type_id')
            ->selectRaw('programs.program_type_id as type_id, count(*) as registered')
            ->selectRaw('count(*) filter (where program_registrations.attended_at is not null or program_registrations.completed_at is not null) as completed')
            ->get()->keyBy('type_id');

        // تقدّم المسجلين المؤكدين في عنصر برنامجهم بعد التسجيل وحده (مراجعة المشرف على #126): صفحات البرنامج في التسجيل نفسه،
        // وثواني الاستماع والمشاهدة مطروحاً منها ما كان عند التسجيل
        $progress = DB::table('program_registrations')
            ->join('programs', 'programs.id', '=', 'program_registrations.program_id')
            ->leftJoin('library_progress', fn ($join) => $join->on('library_progress.user_id', '=', 'program_registrations.user_id')
                ->on('library_progress.library_item_id', '=', 'programs.library_item_id'))
            ->whereNull('programs.deleted_at')
            ->whereNotNull('programs.library_item_id')
            ->where('program_registrations.status', RegistrationStatus::Confirmed->value)
            ->groupBy('programs.program_type_id')
            ->selectRaw('programs.program_type_id as type_id, coalesce(sum(program_registrations.pages_count), 0) as pages')
            ->selectRaw('coalesce(sum(greatest(coalesce(library_progress.seconds_consumed, 0) - program_registrations.seconds_baseline, 0)), 0) as seconds')
            ->get()->keyBy('type_id');

        $rows = [];

        foreach (ProgramType::query()->ordered()->get(['id', 'name', 'slug']) as $type) {
            $kind = LibraryProgramKind::tryFrom($type->slug);
            $registered = (int) ($registrations[$type->id]->registered ?? 0);
            $completed = (int) ($registrations[$type->id]->completed ?? 0);

            $rows[] = self::row($type->slug, $type->name, $registered, $completed,
                $kind === LibraryProgramKind::Reading ? (int) ($progress[$type->id]->pages ?? 0) : 0,
                $kind !== null && $kind !== LibraryProgramKind::Reading ? intdiv((int) ($progress[$type->id]->seconds ?? 0), 60) : 0,
            );
        }

        return $rows;
    }

    /**
     * @return array{key: string, label: string, registered: int, completed: int, rate: float, pages: int, minutes: int}
     */
    private static function row(string $key, string $label, int $registered, int $completed, int $pages, int $minutes): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'registered' => $registered,
            'completed' => $completed,
            'rate' => $registered > 0 ? round($completed * 100 / $registered, 1) : 0.0,
            'pages' => $pages,
            'minutes' => $minutes,
        ];
    }
}
