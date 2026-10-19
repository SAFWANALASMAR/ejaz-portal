// نافذة المساعد الذكي (resources/views/partials/assistant.blade.php): تبقى بمحادثتها عبر التنقّل السريع (@persist)،
// فالمستمعات مفوّضة على document مرة واحدة. والأجوبة نص (textContent) في قوالب الصفحة، والروابط من مصادر الخادم وحدها
// ومساراتها داخلية، فلا HTML من الخادم ولا من النموذج. ولا سكربت مضمّن ولا eval، فتسمح بها سياسة أمان المحتوى.

const root = () => document.querySelector('[data-assistant]');
const panel = () => document.getElementById('assistant-panel');
const toggle = () => root()?.querySelector('[data-assistant-toggle]');
const mobile = () => window.matchMedia('(max-width: 639px)').matches;

// على الجوال تملأ النافذة الشاشة: ما خلفها inert، فلا تصل إليه لوحة المفاتيح ولا القارئ الصوتي (مراجعة المشرف، ٥).
// وعلى الحاسوب نافذة جانبية لا تحجب الصفحة. ويُعاد بعد التنقّل السريع، لأن الصفحة الجديدة عناصر جديدة
function shield(on) {
    const own = root()?.closest('body > *');

    [...document.body.children].forEach((child) => {
        if (child !== own) {
            child.inert = on;
        }
    });
}

function setOpen(open) {
    const element = panel();
    const button = toggle();

    if (!element || !button) {
        return;
    }

    element.hidden = !open;
    button.setAttribute('aria-expanded', String(open));
    // على الجوال تملأ النافذة الشاشة، فلا يتحرك ما خلفها
    document.documentElement.classList.toggle('max-sm:overflow-hidden', open);
    shield(open && mobile());

    if (open) {
        element.querySelector('#assistant-question')?.focus();
    } else {
        button.focus();
    }
}

function template(name) {
    return root().querySelector(`[data-assistant-template="${name}"]`).content.firstElementChild.cloneNode(true);
}

function append(node) {
    const log = root().querySelector('[data-assistant-log]');
    log.append(node);
    log.scrollTop = log.scrollHeight;

    return node;
}

// رابط داخلي من مصادر الخادم: مسار يبدأ بـ / وحده، ولا //، فلا يصير رابطاً خارجياً أو javascript:
const safePath = (url) => typeof url === 'string' && url.startsWith('/') && !url.startsWith('//') && !url.includes('\\');

function link(element, source) {
    element.href = source.url;

    if (source.navigate) {
        element.setAttribute('wire:navigate', '');
    }

    return element;
}

function showAnswer(data) {
    const bubble = template('answer');
    bubble.querySelector('[data-text]').textContent = data.answer;

    const sources = (Array.isArray(data.sources) ? data.sources : []).filter((source) => safePath(source.url));

    if (sources.length > 0) {
        const list = bubble.querySelector('[data-source-list]');

        sources.forEach((source) => {
            const item = document.createElement('li');
            const anchor = link(document.createElement('a'), source);
            anchor.textContent = source.title;
            anchor.className = 'text-primary underline decoration-accent underline-offset-4 hover:text-primary-dark';
            item.append(anchor);
            list.append(item);
        });

        bubble.querySelector('[data-sources]').hidden = false;

        // «اذهب إلى الصفحة» لأول مصدرين، بالتنقّل السريع متى سمح به الرابط
        const go = bubble.querySelector('[data-go]');
        const labelTemplate = root().querySelector('[data-assistant-template="go-label"]').content.textContent.trim();

        sources.slice(0, 2).forEach((source, index) => {
            const button = link(template('go'), source);
            button.setAttribute('aria-label', labelTemplate.replace('__TITLE__', source.title));

            if (index > 0) {
                button.querySelector('[data-label]').textContent = source.title;
            }

            go.append(button);
        });

        go.hidden = false;
    }

    bubble.querySelector('[data-contact]').hidden = !data.contact;

    if (data.id) {
        const rating = bubble.querySelector('[data-rating]');
        rating.dataset.answer = data.id;
        rating.hidden = false;
    }

    append(bubble);
}

function showMessage(text, contact = true) {
    showAnswer({ answer: text, sources: [], contact });
}

async function post(url, body) {
    return fetch(url, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify(body),
    });
}

let busy = false;

async function ask(question) {
    const widget = root();
    const form = widget.querySelector('[data-assistant-form]');
    const send = form.querySelector('[data-assistant-send]');
    const input = form.querySelector('#assistant-question');
    const text = question.trim();

    if (busy || text.length < 2) {
        return;
    }

    busy = true;
    send.disabled = true;
    input.value = '';
    updateRemaining();

    const mine = template('question');
    mine.querySelector('[data-text]').textContent = text;
    append(mine);
    widget.querySelector('[data-assistant-suggestions]')?.remove();

    const waiting = document.createElement('p');
    waiting.className = 'text-sm text-muted';
    waiting.setAttribute('role', 'status');
    waiting.textContent = widget.dataset.labelSending;
    append(waiting);

    try {
        // المسار وحده، بلا استعلام ولا جزء، ليعرف المساعد الصفحة الحالية
        const response = await post(widget.dataset.askUrl, { question: text, page: window.location.pathname });
        const data = await response.json().catch(() => ({}));
        waiting.remove();

        if (response.ok) {
            showAnswer(data);
        } else if (response.status === 404) {
            // أُطفئ المساعد من اللوحة أثناء الجلسة
            widget.hidden = true;
        } else if (response.status === 422 || response.status === 429) {
            showMessage(data.message || widget.dataset.labelError);
        } else {
            showMessage(widget.dataset.labelError);
        }
    } catch {
        waiting.remove();
        showMessage(widget.dataset.labelError);
    } finally {
        busy = false;
        send.disabled = false;
        input.focus();
    }
}

async function rate(button) {
    const rating = button.closest('[data-rating]');
    const widget = root();

    if (!rating?.dataset.answer) {
        return;
    }

    const response = await post(`${widget.dataset.rateUrl}/${encodeURIComponent(rating.dataset.answer)}/rating`, { rating: button.dataset.rate }).catch(() => null);

    if (response?.ok) {
        rating.querySelectorAll('[data-rate]').forEach((other) => other.setAttribute('aria-pressed', String(other === button)));
        rating.querySelector('[data-rated]').textContent = widget.dataset.labelThanks;
    }
}

function updateRemaining() {
    const widget = root();
    const input = widget?.querySelector('#assistant-question');
    const output = widget?.querySelector('[data-assistant-remaining]');

    if (!input || !output) {
        return;
    }

    const left = Number(widget.dataset.max) - input.value.length;
    // يظهر العدّاد قرب الحد وحده، فلا يُعلَن مع كل حرف
    output.textContent = left <= 50 ? widget.dataset.labelRemaining.replace(':count', String(Math.max(0, left))) : '';
}

document.addEventListener('click', (event) => {
    if (!root()) {
        return;
    }

    if (event.target.closest('[data-assistant-toggle]')) {
        setOpen(panel().hidden);

        return;
    }

    if (event.target.closest('[data-assistant-close]')) {
        setOpen(false);

        return;
    }

    const suggestion = event.target.closest('[data-assistant-suggestion]');

    if (suggestion) {
        ask(suggestion.textContent);

        return;
    }

    const rateButton = event.target.closest('[data-rate]');

    if (rateButton && root().contains(rateButton)) {
        rate(rateButton);

        return;
    }

    // الانتقال إلى صفحة من المصادر على الجوال يغلق النافذة التي تملأ الشاشة، فتظهر الصفحة
    if (event.target.closest('#assistant-panel a[href]') && mobile()) {
        panel().hidden = true;
        toggle()?.setAttribute('aria-expanded', 'false');
        document.documentElement.classList.remove('max-sm:overflow-hidden');
        shield(false);
    }
});

// الصفحة الجديدة بعد التنقّل السريع: تُحجب إن بقيت النافذة مفتوحة على الجوال (زر الرجوع مثلاً)
document.addEventListener('livewire:navigated', () => {
    const element = panel();

    shield(Boolean(element && !element.hidden && mobile()));
});

document.addEventListener('submit', (event) => {
    if (event.target.matches?.('[data-assistant-form]')) {
        event.preventDefault();
        ask(event.target.querySelector('#assistant-question').value);
    }
});

document.addEventListener('keydown', (event) => {
    const element = panel();

    if (!element || element.hidden) {
        return;
    }

    if (event.key === 'Escape') {
        event.stopPropagation();
        setOpen(false);

        return;
    }

    // Enter يرسل، وShift+Enter سطر جديد
    if (event.key === 'Enter' && !event.shiftKey && !event.isComposing && event.target.id === 'assistant-question') {
        event.preventDefault();
        ask(event.target.value);
    }
});

document.addEventListener('input', (event) => {
    if (event.target.id === 'assistant-question') {
        updateRemaining();
    }
});
