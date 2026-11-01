<?php

namespace App\Actions\Assembly;

use App\Actions\Accounts\BuildOversight;
use App\Enums\AgendaItemStatus;
use App\Enums\MeetingBody;
use App\Enums\MeetingStatus;
use App\Models\AssemblyMembership;
use App\Models\Meeting;
use App\Models\MeetingAgendaItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * بوابة مجلس الإدارة والجمعية العمومية في صفحة الجمعية العمومية لمن يصل إلى منطقة الأعضاء (Final-Board — البند B1، المرحلة د
 * من #111). عرض لما هو قائم بلا بيانات جديدة ولا صلاحية جديدة:
 * - اجتماعات الجمعية العمومية لكل عضو (MeetingPolicy::attend)، ومعها اجتماعات المجلس ولجانه لمن يراها في اللوحة
 *   (Meeting::visibleTo) كما في BuildOversight.
 * - المحاضر المعتمدة للجمعية العمومية (MeetingPolicy::readMinutes)، والقرارات والتكليفات من بنود الاجتماعات التي يراها
 *   في اللوحة وحدها.
 */
class BuildBoardPortal
{
    public const UPCOMING = 3;

    public const LATEST = 4;

    /**
     * @return array{
     *     stats: array<string, int|null>,
     *     upcoming: Collection<int, Meeting>,
     *     minutes: Collection<int, Meeting>,
     *     decisions: Collection<int, MeetingAgendaItem>|null,
     * }
     */
    public function handle(User $user): array
    {
        $decisions = $user->can('meetings.view')
            ? fn (): Builder => MeetingAgendaItem::query()->whereNotNull('decision')->whereHas('meeting', fn (Builder $query) => $query->visibleTo($user))
            : null;

        // السنة بتوقيت العرض (الرياض) محوّلةً إلى UTC، فلا تنحرف اجتماعات ليلة رأس السنة
        $year = now(config('app.display_timezone'));

        return [
            'stats' => [
                // عدد الأعضاء الفعّالين لمديري سجل الأعضاء في اللوحة وحدهم (1 نوفمبر 2026)، والعام منه في صفحة الحوكمة
                'members' => $user->can('assembly_members.update') ? AssemblyMembership::activeUsers()->count() : null,
                'meetings_this_year' => $this->meetings($user)
                    ->whereBetween('starts_at', [$year->copy()->startOfYear()->utc(), $year->copy()->endOfYear()->utc()])
                    ->where('status', '!=', MeetingStatus::Cancelled->value)->count(),
                'approved_minutes' => Meeting::query()->generalAssembly()->whereNotNull('minutes_file')->whereNotNull('minutes_approved_at')->count(),
                // التكليفات قيد التنفيذ لمن يرى البنود في اللوحة وحده، وإلا لا تظهر البطاقة
                'open_assignments' => $decisions !== null ? $decisions()->where('status', AgendaItemStatus::InProgress->value)->count() : null,
            ],
            'upcoming' => $this->meetings($user)->with('committee')->withCount('agendaItems')
                ->where('status', MeetingStatus::Scheduled->value)
                // والجاري منها يبقى بزر تيمز حتى ثلاث ساعات بعد بدئه، كما في رئيسية المشرف العام
                ->where('starts_at', '>', now()->subHours(BuildOversight::RUNNING_HOURS))
                ->orderBy('starts_at')
                ->limit(self::UPCOMING)
                ->get(),
            'minutes' => Meeting::query()->generalAssembly()->whereNotNull('minutes_file')->whereNotNull('minutes_approved_at')
                ->orderByDesc('minutes_approved_at')->limit(self::LATEST)->get(),
            'decisions' => $decisions !== null ? $decisions()->with('meeting')->latest('id')->limit(self::LATEST)->get() : null,
        ];
    }

    /**
     * الاجتماعات التي يطّلع عليها العضو: الجمعية العمومية لكل عضو، والمجلس ولجانه لمن يراها في اللوحة.
     *
     * @return Builder<Meeting>
     */
    private function meetings(User $user): Builder
    {
        return Meeting::query()->where(fn (Builder $query) => $query
            ->where('body', MeetingBody::GeneralAssembly->value)
            ->when($user->can('meetings.view'), fn (Builder $query) => $query->orWhere(fn (Builder $query) => $query
                ->where('body', '!=', MeetingBody::GeneralAssembly->value)
                ->visibleTo($user))));
    }
}
