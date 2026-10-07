<?php

namespace App\Http\Controllers\Account;

use App\Actions\Programs\CancelProgramRegistration;
use App\Actions\Programs\RegisterForProgram;
use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Programs\RegisterForProgramRequest;
use App\Models\Program;
use App\Models\ProgramRegistration;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * التسجيل في برنامج، و«برامجي»: التسجيلات بحالاتها وإلغاؤها. كل تسجيل لصاحبه فقط.
 */
class ProgramRegistrationController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $registrations = ProgramRegistration::query()
            ->whereBelongsTo($user)
            ->with('program.type')
            ->latest('id')
            ->paginate(15);

        return view('account.programs.index', ['registrations' => $registrations]);
    }

    public function create(Request $request, string $slug): View|RedirectResponse
    {
        $program = Program::query()->published()->where('slug', $slug)->firstOrFail();

        $registered = ProgramRegistration::query()
            ->where('program_id', $program->id)
            ->whereBelongsTo($request->user())
            ->where('status', '!=', RegistrationStatus::Cancelled->value)
            ->exists();

        if ($registered || ! $program->registrationIsOpen()) {
            return redirect()->route('programs.show', $program->slug);
        }

        return view('programs.register', ['program' => $program]);
    }

    public function store(RegisterForProgramRequest $request, RegisterForProgram $register): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var array<string, mixed> $answers */
        $answers = $request->validated('answers') ?? [];
        $registration = $register->handle($user, $request->program(), $answers);

        return redirect()->route('programs.show', $request->program()->slug)
            ->with('status', __($registration->status === RegistrationStatus::Confirmed ? 'programs.registered' : 'programs.waitlisted'));
    }

    public function cancel(Request $request, ProgramRegistration $registration, CancelProgramRegistration $cancel): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        // تسجيل غيره كأنه غير موجود
        abort_unless($registration->user_id === $user->id, 404);

        $cancel->handle($user, $registration);

        return redirect()->route('account.programs.index')->with('status', __('programs.cancelled'));
    }
}
