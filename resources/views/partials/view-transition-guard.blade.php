{{--
    انتقال المستندات (@view-transition في visual.css) يتخطاه المتصفح أحياناً بعد التحميل الكامل على اتصال بطيء، فيرفض وعده برفض غير
    معالَج: «Transition was aborted because of invalid state. Page already revealed» (قبول ملاحظات 4 نوفمبر، 5 نوفمبر 2026). لا أثر له في
    الصفحة، والانتقال وحده يُتخطى، لكنه يملأ وحدة التحكم. فيُلتقط رفض الانتقال وحده ولا يُمس غيره من الأخطاء:
    - انتقال الصفحة الواردة (pagereveal) بوعوده الثلاثة.
    - ورفض «Transition was aborted» نفسه إن لم يصل إلى الصفحة انتقاله (تخطاه المتصفح قبل pagereveal).
    مضمّن في الرأس قبل أي سكربت، فيسبق كشف الصفحة. ومرة واحدة مع التنقّل السريع (data-navigate-once والعلامة).
--}}
<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}" data-navigate-once data-view-transition-guard>
    (() => {
        if (window.ejazTransitionGuard) {
            return;
        }

        window.ejazTransitionGuard = true;

        const quiet = (promise) => promise?.catch?.(() => {});

        window.addEventListener('pagereveal', (event) => {
            const transition = event.viewTransition;

            if (transition) {
                [transition.ready, transition.finished, transition.updateCallbackDone].forEach(quiet);
            }
        });

        window.addEventListener('unhandledrejection', (event) => {
            const reason = event.reason;

            if (reason instanceof DOMException && reason.name === 'InvalidStateError' && reason.message.startsWith('Transition was aborted')) {
                event.preventDefault();
            }
        });
    })();
</script>
