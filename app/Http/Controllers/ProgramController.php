<?php

namespace App\Http\Controllers;

use App\Enums\RegistrationStatus;
use App\Models\Program;
use App\Models\ProgramRegistration;
use App\Models\ProgramType;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * «الدورات والبرامج» (الصورة 06): تبويبات بالأنواع وبطاقات البرامج المنشورة القادمة أولاً، ثم صفحة
 * البرنامج. وحالة كل برنامج (قريباً، مفتوح، مكتمل، منتهٍ) تُحسب بعدد المؤكدين في الاستعلام نفسه.
 */
class ProgramController extends Controller
{
    private const PER_PAGE = 12;

    public function index(Request $request): View|RedirectResponse
    {
        $types = ProgramType::query()->ordered()->get();
        $active = $request->filled('type') ? $types->firstWhere('slug', (string) $request->query('type')) : null;

        if ($request->filled('type') && $active === null) {
            return redirect()->route('programs.index');
        }

        $programs = Program::query()
            ->published()
            ->withConfirmedCount()
            ->with('type')
            ->when($active, fn (Builder $query) => $query->where('program_type_id', $active->id))
            // القادمة بأقربها موعداً، ثم المنتهية بأحدثها
            ->orderByRaw('case when coalesce(ends_at, starts_at) > now() then 0 else 1 end')
            ->orderByRaw('case when coalesce(ends_at, starts_at) > now() then starts_at end asc')
            ->orderByDesc('starts_at')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        abort_if($programs->currentPage() > max($programs->lastPage(), 1), 404);

        return view('programs.index', ['types' => $types, 'active' => $active, 'programs' => $programs, 'hub' => self::hub($types)]);
    }

    public function show(Request $request, string $slug): Response
    {
        $program = Program::query()->published()->withConfirmedCount()->with(['type', 'initiative'])->where('slug', $slug)->firstOrFail();

        $registration = $request->user() === null ? null : ProgramRegistration::query()
            ->where('program_id', $program->id)
            ->where('user_id', $request->user()->id)
            ->where('status', '!=', RegistrationStatus::Cancelled->value)
            ->first();

        $response = response()->view('programs.show', ['program' => $program, 'registration' => $registration]);

        // الصفحة لصاحب الحساب قد تحمل رابط اجتماعه، فلا تُحفظ في ذاكرة متصفح ولا وسيط
        return $request->user() === null ? $response : $response->header('Cache-Control', 'private, no-store');
    }

    /**
     * بطاقات محور «الدورات والبرامج»: نوعا المحور بأسمائهما من اللوحة متى وُجدا، ثم المسابقات.
     *
     * @param  Collection<int, ProgramType>  $types
     * @return list<array{label: string, url: string, icon: string}>
     */
    private static function hub(Collection $types): array
    {
        $hub = [];

        foreach (ProgramType::HUB as $slug => $icon) {
            $type = $types->firstWhere('slug', $slug);

            if ($type !== null) {
                $hub[] = ['label' => $type->name, 'url' => route('programs.index', ['type' => $slug]), 'icon' => $icon];
            }
        }

        $hub[] = ['label' => __('programs.hub.competitions'), 'url' => route('competitions.index'), 'icon' => 'trophy'];

        return $hub;
    }
}
