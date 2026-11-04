<?php

namespace App\Http\Controllers;

use App\Enums\LibraryProgramKind;
use App\Enums\RegistrationStatus;
use App\Models\Program;
use App\Models\ProgramRegistration;
use App\Models\ProgramType;
use App\Support\LibraryProgramProgress;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * «الدورات والبرامج» (الصورة 06): شريط بالأنواع ومقطع لكل نوع بالبرامج المنشورة القادمة أولاً، ثم صفحة
 * البرنامج. وحالة كل برنامج (قريباً، مفتوح، مكتمل، منتهٍ) تُحسب بعدد المؤكدين في الاستعلام نفسه.
 */
class ProgramController extends Controller
{
    private const PER_PAGE = 12;

    /** بطاقات كل نوع في مقطعه، وما زاد في «عرض الكل» */
    public const PER_SECTION = 6;

    /** القادمة بأقربها موعداً، ثم المنتهية بأحدثها */
    private const ORDER = 'case when coalesce(ends_at, starts_at) > now() then 0 else 1 end, case when coalesce(ends_at, starts_at) > now() then starts_at end asc, starts_at desc, id';

    public function index(Request $request): View|RedirectResponse
    {
        $types = ProgramType::query()->ordered()->get();
        // والنوع المصفوفة (?type[]=) نوع مجهول يُحوَّل كغيره، لا خطأ في الخادم
        $type = $request->query('type');
        $active = is_string($type) && $type !== '' ? $types->firstWhere('slug', $type) : null;

        if ($request->filled('type') && $active === null) {
            return redirect()->route('programs.index');
        }

        // بلا نوع: مقطع لكل نوع في الصفحة نفسها، وشريط الأيقونات يُنزل إليه (ملاحظات المدير التنفيذي، 4 نوفمبر 2026). والنوع
        // بقائمته الكاملة المرقّمة في ?type= لـ«عرض الكل» والروابط القديمة
        if ($active === null) {
            abort_if($request->integer('page', 1) > 1, 404);

            // استعلام واحد لكل الأنواع مهما كثرت: ترتيب البرنامج في نوعه (row_number)، وواحد زائد على المقطع يُظهر «عرض الكل»
            $ranked = Program::query()->published()->withConfirmedCount()
                ->selectRaw('row_number() over (partition by program_type_id order by '.self::ORDER.') as section_rank');
            $byType = Program::query()->fromSub($ranked, 'programs')->where('section_rank', '<=', self::PER_SECTION + 1)
                ->orderByRaw(self::ORDER)->get()->groupBy('program_type_id');

            $sections = $types->map(fn (ProgramType $programType): array => [
                'type' => $programType,
                'programs' => ($byType->get($programType->id) ?? new Collection)
                    ->each(fn (Program $program) => $program->setRelation('type', $programType)),
            ]);

            return view('programs.index', ['types' => $types, 'active' => null, 'sections' => $sections, 'programs' => null]);
        }

        $programs = self::listing()->where('program_type_id', $active->id)->paginate(self::PER_PAGE)->withQueryString();

        abort_if($programs->currentPage() > max($programs->lastPage(), 1), 404);

        // نوع كل برنامج من النوع المحمّل، لا باستعلام ثانٍ عنه
        $programs->each(fn (Program $program) => $program->setRelation('type', $active));

        return view('programs.index', ['types' => $types, 'active' => $active, 'sections' => null, 'programs' => $programs]);
    }

    /**
     * البرامج المنشورة: القادمة بأقربها موعداً، ثم المنتهية بأحدثها.
     *
     * @return Builder<Program>
     */
    private static function listing(): Builder
    {
        return Program::query()->published()->withConfirmedCount()->orderByRaw(self::ORDER);
    }

    public function show(Request $request, string $slug): Response
    {
        $program = Program::query()->published()->withConfirmedCount()->with(['type', 'initiative', 'libraryItem'])->where('slug', $slug)->firstOrFail();

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
     * قارئ كتاب برنامج «قراءة كتاب» للمسجل المؤكد فيه وحده: الكتاب في قارئ الصفحة دائماً فتُحصى صفحاته
     * (docs/decisions/2026-11-02-reading-program.md). ولا يُخزَّن.
     */
    public function read(Request $request, string $slug): Response
    {
        $program = Program::query()->published()->with(['type', 'libraryItem'])->where('slug', $slug)->firstOrFail();
        $item = $program->libraryItem;

        abort_unless($program->libraryKind() === LibraryProgramKind::Reading && $item !== null && $item->is_published && ! $item->trashed(), 404);
        // مسجل مؤكد في هذا البرنامج نفسه، لا في برنامج آخر بالكتاب نفسه
        $registered = ProgramRegistration::query()->where('program_id', $program->id)->where('user_id', $request->user()?->id)
            ->where('status', RegistrationStatus::Confirmed->value)->exists();
        abort_unless($registered && LibraryProgramProgress::readsInProgram($request->user(), $item), 403);

        return response()->view('programs.read', ['program' => $program, 'item' => $item])->header('Cache-Control', 'private, no-store');
    }
}
