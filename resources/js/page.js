// التنقّل السريع (wire:navigate — docs/decisions.md): Livewire يضع الصفحة التالية مكان الحالية بلا إعادة تحميل،
// فلا تُعاد السكربتات. فما يمسح عناصر الصفحة يُسجَّل هنا بـ onPage: يعمل عند أول تحميل، وبعد كل تنقّل تغيّرت
// فيه الصفحة، ويأخذ إشارة (AbortSignal) تُلغى حين تحل التالية مكانها: تزيل المستمعات المسجلة بها، ويوقف عندها
// ما يربطه بها من مؤقتات ومراقبات وطلبات. والمستمعات المفوّضة على document تُسجَّل مرة واحدة خارجها.

const setups = [];
const leaving = [];
let controller = new AbortController();
let currentBody = null;
let currentPage = null;
// هل حمل هذا المستند صفحة لمسجّل دخول قط؟ نسخ صفحات الحساب لا توجد إلا في مستند كان فيه، ولو انتهت الجلسة بعدها
// (خمول أو خروج من تبويب آخر) فصارت الصفحة الحالية لزائر
let hadSession = false;

export function onPage(setup) {
    setups.push(setup);
}

// قبل مغادرة الصفحة، وقبل أن يحفظ Livewire نسختها لزر الرجوع (القوائم المفتوحة مثلاً تُغلق فيها)
export function onLeave(callback) {
    leaving.push(callback);
}

function run() {
    currentBody = document.body;
    currentPage = window.location.pathname + window.location.search;
    hadSession ||= signedIn();
    // كل تهيئة وحدها: خطأ في واحدة لا يوقف ما بعدها (مراجعة المشرف على #157)، ويبقى في وحدة التحكم
    setups.forEach((setup) => {
        try {
            setup(controller.signal);
        } catch (error) {
            console.error(error);
        }
    });
}

const signedIn = () => document.querySelector('meta[name="signed-in"]') !== null;

// المنطقة الحية التي يُعلَن فيها عنوان الصفحة بعد التنقّل (في القالب العام)
const announcer = () => document.querySelector('[data-page-announcer]');

// شريط التقدّم (html[data-navigating] في app.css) متى طال التنقّل أكثر من 150 ملّي ثانية، كشريط Livewire المعطّل
// في القالب لأن دوره في ARIA غير صالح. وLivewire يستبدل سمات html من الصفحة الجديدة فيزول معها أيضاً. وطلب
// يفشل والجهاز متصل لا يُتمّه Livewire ولا يطلق له حدثاً، فيزول الشريط بعد مهلة قصوى
let progress = null;
let progressLimit = null;

// انتقال الصفحات بالشعار (أمر التصور النهائي، البند R25 — Final-Transition): طبقة بتدرج الهوية الداكن ورمز الشعار مؤشراً للتحميل
// (partials/page-transition، @persist فتبقى هي نفسها عبر التنقّل). تظهر بعد 400 ملّي ثانية من التنقّل وحدها فلا تومض، وتتلاشى
// فور حلول الصفحة (leaving). والحركة كلها في CSS، وتقليلها يتركه ثابتاً
const transition = () => document.querySelector('[data-page-transition]');
const TRANSITION_DELAY = 400;
// تلاشي الطبقة بعد حلول الصفحة، ومثله في concept.css (.page-transition[data-state='leaving'])
const TRANSITION_FADE = 120;
const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches || document.documentElement.hasAttribute('data-motion-off');
let shownAt = 0;
let transitionTimer = null;

// والطبقة لا تُخفى قبل حلول الصفحة الجديدة (livewire:navigated): كانت تُخفى قسراً بعد أربع ثوانٍ (TRANSITION_LIMIT، مراجعة المشرف
// على #111)، فإن أبطأ الخادم عادت الصفحة القديمة ظاهرة حتى يرد ثم تبدّلت وحدها (عيب صوّره صفوان على رابط العرض، 4 نوفمبر 2026).
// فبعد أربع ثوانٍ يظهر تحت الشعار سطر «جارٍ التحميل…» والطبقة باقية. وتُخفى عند حلول الصفحة، أو فشل التنقّل (مهلة شريط
// التقدّم القصوى، 15 ثانية: Livewire لا يطلق حدثاً لطلب فشل)، أو العودة من ذاكرة الرجوع (bfcache) بنسختها ظاهرةً
const TRANSITION_SLOW = 4000;
let transitionSlow = null;

const slowLine = (overlay) => overlay.querySelector('[data-transition-slow]');

function showTransition() {
    const overlay = transition();

    if (!overlay) {
        return;
    }

    clearTimeout(transitionTimer);
    clearTimeout(transitionSlow);
    overlay.hidden = false;
    overlay.dataset.state = 'loading';
    overlay.setAttribute('aria-busy', 'true');
    shownAt = performance.now();
    transitionSlow = setTimeout(() => {
        const line = slowLine(overlay);

        if (line) {
            line.hidden = false;
        }
    }, TRANSITION_SLOW);
}

function hideTransition() {
    const overlay = transition();

    clearTimeout(transitionTimer);
    clearTimeout(transitionSlow);
    shownAt = 0;

    if (overlay) {
        overlay.hidden = true;
        overlay.setAttribute('aria-busy', 'false');
        delete overlay.dataset.state;

        const line = slowLine(overlay);

        if (line) {
            line.hidden = true;
        }
    }
}

function finishTransition() {
    const overlay = transition();

    if (!overlay || shownAt === 0) {
        return;
    }

    // تختفي فور حلول الصفحة الجديدة بلا انتظار الحركة (ملاحظات المدير التنفيذي، ليلة 5 نوفمبر 2026، البند ٥): كانت تبقى نحو 200 ملّي
    // ثانية بعد حلولها (حد أدنى 200 من ظهورها، ثم تلاشٍ 180). الآن تلاشٍ قصير (TRANSITION_FADE) يبدأ فوراً، ومع تقليل الحركة تُخفى فوراً
    if (reducedMotion()) {
        hideTransition();

        return;
    }

    shownAt = 0;
    clearTimeout(transitionTimer);
    clearTimeout(transitionSlow);
    overlay.dataset.state = 'leaving';
    overlay.setAttribute('aria-busy', 'false');
    transitionTimer = setTimeout(hideTransition, TRANSITION_FADE);
}

function stopProgress() {
    clearTimeout(progress);
    clearTimeout(progressLimit);
    document.documentElement.removeAttribute('data-navigating');
    finishTransition();
}

export function start() {
    // نشر جديد غيّر أصول Vite (البصمة في اسم الملف): يضيف Livewire الجديدة إلى الرأس عند التنقّل التالي فتعمل
    // مع القديمة، فتُحمَّل الصفحة كاملة بأصولها الجديدة بدل تهيئة مزدوجة
    if (window.ejazPageStarted) {
        window.location.reload();

        return;
    }

    window.ejazPageStarted = true;
    run();

    // ذاكرة الرجوع في المتصفح (bfcache) تحفظ المستند كله، ورؤوسه من أول صفحة حُمّلت كاملاً (عامة عادة)، فتعرض
    // صفحات الحساب التي تُنقّل إليها بعد الخروج. فصفحة لمسجّل دخول تُطلب من الخادم عند الرجوع إليها
    window.addEventListener('pageshow', (event) => {
        if (!event.persisted) {
            return;
        }

        clearTimeout(progress);
        clearTimeout(progressLimit);
        document.documentElement.removeAttribute('data-navigating');
        hideTransition();

        if (hadSession) {
            window.location.reload();
        }
    });

    document.addEventListener('livewire:navigate', (event) => {
        // منعه حارس قبله (رفع مقطع جارٍ في المسابقة): لا تنقّل
        if (event.defaultPrevented) {
            return;
        }

        const { url, history, cached } = event.detail ?? {};

        // وكذلك نسخ Livewire المحفوظة لزري الرجوع والتقدّم: للمسجّل تُطلب الصفحة من الخادم كاملة، فبياناته
        // وحالات طلباته ورموز الحماية في نماذجه كما هي الآن، ولا تعود نسخة قديمة من نموذج يُحفظ تلقائياً
        // (المسابقة) فيمسح الحفظ ما كُتب بعدها. والزائر بلا حساب يبقى على النسخ المحفوظة، والرابط داخل الصفحة نفسها كما هو
        if (history && cached && hadSession && url && url.pathname + url.search !== currentPage) {
            event.preventDefault();
            window.location.reload();

            return;
        }

        stopProgress();
        // والطبقة بعد 400 لا 150: التنقّل العادي (خادم سريع) يحل بلا طبقة أصلاً (البند ٥). ولا جلب مسبق عند المرور: أُسقط لأنه يُعدّ زيارة ويضاعف الطلبات في الذروة
        progress = setTimeout(() => {
            document.documentElement.setAttribute('data-navigating', '');
            progress = setTimeout(showTransition, TRANSITION_DELAY - 150);
        }, 150);
        progressLimit = setTimeout(stopProgress, 15000);
    });

    document.addEventListener('livewire:navigating', () => {
        // الإعلان السابق يُمسح قبل أن يحفظ Livewire نسخة الصفحة، فيُعلَن عنوانها من جديد عند الرجوع إليها
        announcer()?.replaceChildren();
        leaving.forEach((callback) => callback());
    });

    // يُطلق أيضاً عند أول تحميل، وعند رابط داخل الصفحة نفسها، وعند الرجوع إليها دون تغيّرها: لا تهيئة مكررة
    document.addEventListener('livewire:navigated', () => {
        stopProgress();

        if (document.body === currentBody) {
            return;
        }

        // إشارة الصفحة السابقة تُلغى بعد أن حلّت الجديدة مكانها لا في navigating: فإن أُطلق ذلك ولم تتبدّل
        // الصفحة بقيت وظائفها (الحفظ التلقائي، والعدّاد) تعمل
        controller.abort();
        controller = new AbortController();
        run();

        // التركيز إلى عنوان الصفحة الجديدة (h1)، وإلا المحتوى الرئيسي بتسميته، فيقرؤها قارئ الشاشة وتبدأ منها
        // لوحة المفاتيح لا من آخر الصفحة السابقة. وعنصر autofocus يبقى له التركيز
        if (!document.activeElement || document.activeElement === document.body) {
            const heading = document.querySelector('#main h1');

            if (heading && !heading.hasAttribute('tabindex')) {
                heading.tabIndex = -1;
            }

            (heading ?? document.getElementById('main'))?.focus({ preventScroll: true });
        }

        // وعنوان التبويب (يحدّثه Livewire من الصفحة الجديدة) يُعلَن في المنطقة الحية المهذبة، بعد لحظة يسجّلها
        // فيها قارئ الشاشة لأنها جديدة مع الصفحة
        const region = announcer();

        if (region) {
            setTimeout(() => {
                region.textContent = document.title;
            }, 100);
        }
    });
}
