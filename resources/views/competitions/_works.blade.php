{{--
    «رفع الأعمال (صور – PDF – روابط)» في نموذج المشاركة: بالأنواع المسموحة في المسابقة وحدها. ونماذجه
    خارج نموذج الإجابات (لا تتداخل النماذج). والفحص كله في AddSubmissionWork.
--}}
@php
    use App\Enums\WorkType;

    $types = $competition->workTypeList();
@endphp

@if ($types !== [])
    <section class="mt-6 space-y-5 rounded-2xl border border-line/70 bg-white p-6 shadow-xs" aria-labelledby="works-heading">
        <div>
            <h2 id="works-heading" class="text-lg font-bold text-primary">{{ __('competitions.works.heading') }}</h2>
            @if ($editable)
                <p class="mt-1 text-sm text-muted">{{ __('competitions.works.hint', ['max' => config('competitions.works.max_items')]) }}</p>
            @endif
        </div>

        @if ($submission->works->isEmpty())
            <p class="text-sm text-muted">{{ __('competitions.works.empty') }}</p>
        @else
            <ul class="space-y-2">
                @foreach ($submission->works as $work)
                    <li class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-line px-4 py-3">
                        <span class="text-sm font-semibold text-ink">{{ $work->type->getLabel() }}</span>
                        @if ($work->type === WorkType::Link)
                            <a href="{{ $work->url }}" target="_blank" rel="noopener noreferrer nofollow" dir="ltr" class="min-w-0 flex-1 truncate text-sm text-primary underline">{{ $work->url }}</a>
                        @elseif ($work->type === WorkType::Pdf)
                            <a href="{{ route('competitions.works.show', $work) }}" download class="text-sm text-primary underline">{{ __('competitions.works.download') }}</a>
                        @else
                            <a href="{{ route('competitions.works.show', $work) }}" target="_blank" rel="noopener" class="text-sm text-primary underline">{{ __('competitions.works.open') }}</a>
                        @endif
                        @if ($editable)
                            <form method="POST" action="{{ route('competitions.works.destroy', $work) }}">
                                @csrf
                                @method('DELETE')
                                <x-button variant="link" data-confirm="{{ __('competitions.works.remove_confirm') }}">{{ __('competitions.works.remove') }}</x-button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($editable)
            @foreach (['type', 'file', 'url'] as $field)
                @error($field)
                    <x-alert type="error">{{ $message }}</x-alert>
                @enderror
            @endforeach

            <div class="grid gap-4 md:grid-cols-3">
                @foreach ($types as $type)
                    <form method="POST" action="{{ route('competitions.works.store', $competition->slug) }}" enctype="multipart/form-data" class="space-y-3 rounded-xl bg-surface p-4">
                        @csrf
                        <input type="hidden" name="type" value="{{ $type->value }}">
                        <label class="block">
                            <span class="text-sm font-semibold text-ink">{{ __('competitions.works.'.$type->value) }}</span>
                            @if ($type === WorkType::Link)
                                <input type="url" name="url" required maxlength="2048" pattern="https://.*" dir="ltr"
                                    class="mt-2 block w-full rounded-xl border border-muted/70 bg-white px-4 py-2 text-ink focus:border-primary focus:outline-none">
                            @else
                                <input type="file" name="file" required accept="{{ $type === WorkType::Image ? 'image/jpeg,image/png,image/webp' : 'application/pdf' }}"
                                    class="mt-2 block w-full text-sm text-ink file:me-4 file:rounded-full file:border-0 file:bg-primary file:px-4 file:py-2 file:font-semibold file:text-white">
                            @endif
                        </label>
                        <x-button size="sm" variant="outline">{{ __('competitions.works.add') }}</x-button>
                    </form>
                @endforeach
            </div>
        @endif
    </section>
@endif
