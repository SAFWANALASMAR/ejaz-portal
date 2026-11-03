{{--
    «برامجي» في رئيسية الحساب: برامج المكتبة الجارية بتقدّمها من سجل «قراءاتي» (LibraryProgramProgress)، وعدد «برامج أتممتها» برابط
    «البرامج المسجل بها» (docs/decisions/2026-11-02-reading-program.md). ولا شيء لمن لا برنامج مكتبة له.
--}}
@if ($registrations->isNotEmpty() || $completed > 0)
    <section class="mt-8" aria-labelledby="library-programs" data-dashboard-library-programs>
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 id="library-programs" class="text-lg font-black text-primary-dark">{{ __('programs.mine.library_title') }}</h2>
            <a href="{{ route('account.programs.index') }}" wire:navigate class="text-sm font-semibold text-primary underline decoration-accent underline-offset-4">
                {{ __('programs.mine.completed_count', ['count' => number_format($completed)]) }}
            </a>
        </div>

        <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($registrations as $registration)
                @php($percent = \App\Support\LibraryProgramProgress::for($registration->program, auth()->user(), $registration, $progress[$registration->program->library_item_id] ?? false)->percent())
                <li class="concept-panel flex flex-col gap-2 p-4">
                    <a href="{{ route('programs.show', $registration->program->slug) }}" wire:navigate class="font-semibold text-ink hover:text-primary">{{ $registration->program->title }}</a>
                    <span class="text-sm text-muted">{{ $registration->program->type->name }}</span>
                    <progress max="100" value="{{ $percent }}" aria-label="{{ __('programs.library.progress', ['title' => $registration->program->title]) }}"
                        class="block h-2 w-full appearance-none overflow-hidden rounded-full bg-primary-soft text-primary forced-colors:appearance-auto [&::-moz-progress-bar]:rounded-full [&::-moz-progress-bar]:bg-current [&::-webkit-progress-bar]:bg-primary-soft [&::-webkit-progress-value]:rounded-full [&::-webkit-progress-value]:bg-current">{{ $percent }}%</progress>
                    <span class="text-xs text-muted">{{ __('programs.library.percent', ['percent' => $percent]) }}</span>
                </li>
            @endforeach
        </ul>
    </section>
@endif
