{{-- مقطع السؤال للمحكّم: يُقدَّم عبر مسار يفحص الإسناد عند كل طلب (ReviewVideoController). وعلى S3 رابط تخزين لخمس دقائق،
     فالمشغّل يعيد طلب المسار عند الخطأ أو التوقف فيأخذ توقيعاً جديداً، ويكمل من موضعه، خمس مرات على الأكثر حتى يعود التشغيل
     (البلاغ 185، I2) --}}
<div class="space-y-2">
    <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $label }}</p>
    @if ($video && $video->status === \App\Enums\VideoStatus::Ready)
        @php($src = route('competitions.reviews.video', [$review, $video]))
        <video controls preload="metadata" class="w-full max-w-2xl rounded-lg bg-black" src="{{ $src }}" data-review-video
            x-data="{
                tries: 0,
                reload() {
                    if (this.tries++ >= 5) return;
                    const at = this.$el.currentTime;
                    const playing = ! this.$el.paused;
                    this.$el.addEventListener('loadedmetadata', () => {
                        this.$el.currentTime = at;
                        if (playing) this.$el.play().catch(() => {});
                    }, { once: true });
                    this.$el.src = @js($src) + '?renew=' + Date.now();
                },
            }"
            x-on:error="reload()" x-on:stalled="reload()" x-on:playing="tries = 0"></video>
    @else
        <p class="text-sm text-gray-500">{{ __('competitions.reviews.no_video') }}</p>
    @endif
</div>
