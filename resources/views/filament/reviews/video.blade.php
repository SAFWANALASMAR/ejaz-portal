{{-- مقطع السؤال للمحكّم: يُقدَّم عبر مسار يفحص الإسناد عند كل طلب (ReviewVideoController) --}}
<div class="space-y-2">
    <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $label }}</p>
    @if ($video && $video->status === \App\Enums\VideoStatus::Ready)
        <video controls preload="metadata" class="w-full max-w-2xl rounded-lg bg-black" src="{{ route('competitions.reviews.video', [$review, $video]) }}"></video>
    @else
        <p class="text-sm text-gray-500">{{ __('competitions.reviews.no_video') }}</p>
    @endif
</div>
