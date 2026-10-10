{{-- أعمال المشاركة للمحكّم: الملفات عبر مسار يفحص الإسناد عند كل طلب (ReviewWorkController)، والروابط نصاً برابط آمن --}}
<div class="space-y-2">
    <p class="text-sm font-medium text-gray-950 dark:text-white">{{ __('competitions.works.reviews_heading') }}</p>
    @forelse ($works as $work)
        <div class="flex flex-wrap items-center gap-3 text-sm">
            <span class="font-semibold">{{ $work->type->getLabel() }}</span>
            @if ($work->type === \App\Enums\WorkType::Link)
                <a href="{{ $work->url }}" target="_blank" rel="noopener noreferrer nofollow" dir="ltr" class="text-primary-600 underline">{{ $work->url }}</a>
            @else
                <a href="{{ route('competitions.reviews.work', [$review, $work]) }}" target="_blank" rel="noopener" class="text-primary-600 underline">{{ __('competitions.works.open') }}</a>
            @endif
        </div>
    @empty
        <p class="text-sm text-gray-500">{{ __('competitions.works.reviews_empty') }}</p>
    @endforelse
</div>
