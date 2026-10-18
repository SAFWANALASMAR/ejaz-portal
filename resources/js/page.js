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
    setups.forEach((setup) => setup(controller.signal));
}

const signedIn = () => document.querySelector('meta[name="signed-in"]') !== null;

// المنطقة الحية التي يُعلَن فيها عنوان الصفحة بعد التنقّل (في القالب العام)
const announcer = () => document.querySelector('[data-page-announcer]');

// شريط التقدّم (html[data-navigating] في app.css) متى طال التنقّل أكثر من 150 ملّي ثانية، كشريط Livewire المعطّل
// في القالب لأن دوره في ARIA غير صالح. وLivewire يستبدل سمات html من الصفحة الجديدة فيزول معها أيضاً. وطلب
// يفشل والجهاز متصل لا يُتمّه Livewire ولا يطلق له حدثاً، فيزول الشريط بعد مهلة قصوى
let progress = null;
let progressLimit = null;

function stopProgress() {
    clearTimeout(progress);
    clearTimeout(progressLimit);
    document.documentElement.removeAttribute('data-navigating');
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
        if (event.persisted && hadSession) {
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
        progress = setTimeout(() => document.documentElement.setAttribute('data-navigating', ''), 150);
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
