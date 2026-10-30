// البحث السريع (partials/search-palette): Ctrl+K أو ⌘K أو زر [data-search-open] يفتح النافذة، وEsc يغلقها (سلوك <dialog>).
// يبحث فوراً في أسماء صفحات القائمة، ويقترح البحث بالكلمة في الأقسام التي لها بحث (?q=). الأسهم تنقّل بين النتائج، وEnter
// يفتح المحددة، والتركيز يعود إلى ما فتحها. لا طلب إلى الخادم.

import { onPage } from './page';

// العربية بلا تشكيل ولا فرق بين صور الألف والتاء المربوطة والياء، فيجد «الاعجاز» «الإعجاز»
const normalize = (text) =>
    text
        .toLowerCase()
        .normalize('NFKD')
        .replace(/[ً-ٰٟـ]/g, '')
        .replace(/[إأآا]/g, 'ا')
        .replace(/ة/g, 'ه')
        .replace(/ى/g, 'ي')
        .trim();

let opener = null;

function open() {
    const dialog = document.querySelector('[data-search-dialog]');

    if (!dialog || dialog.open) {
        return;
    }

    opener = document.activeElement;
    dialog.showModal();
    const input = dialog.querySelector('[data-search-input]');
    input.value = '';
    render(dialog, '');
    input.focus();
}

function render(dialog, query) {
    const data = JSON.parse(dialog.querySelector('[data-search-data]').textContent);
    const list = dialog.querySelector('[data-search-results]');
    const empty = dialog.querySelector('[data-search-empty]');
    const term = normalize(query);
    const pages = data.pages.filter((page) => term === '' || normalize(`${page.label} ${page.parent ?? ''}`).includes(term));
    const results = [...pages.map((page) => ({ label: page.parent ? `${page.parent} ‹ ${page.label}` : page.label, url: page.url, icon: 'page' }))];

    if (term !== '') {
        data.sections.forEach((section) => {
            const url = new URL(section.url, window.location.origin);
            url.searchParams.set('q', query.trim());
            results.push({ label: data.in.replace(':q', query.trim()).replace(':section', section.label), url: url.toString(), icon: 'search' });
        });
    }

    list.replaceChildren(
        ...results.map((result, position) => {
            const item = document.createElement('li');
            const link = document.createElement('a');
            link.href = result.url;
            link.id = `search-option-${position}`;
            link.setAttribute('role', 'option');
            link.setAttribute('aria-selected', String(position === 0));
            link.className = 'search-option';
            link.textContent = result.label;
            link.dataset.kind = result.icon;
            item.append(link);

            return item;
        }),
    );
    empty.hidden = pages.length > 0 || term === '';
    dialog.querySelector('[data-search-input]').setAttribute('aria-activedescendant', results.length > 0 ? 'search-option-0' : '');
}

function move(dialog, step) {
    const options = [...dialog.querySelectorAll('[role="option"]')];
    const current = options.findIndex((option) => option.getAttribute('aria-selected') === 'true');
    const next = options[(current + step + options.length) % options.length];

    options.forEach((option) => option.setAttribute('aria-selected', String(option === next)));
    next?.scrollIntoView({ block: 'nearest' });
    dialog.querySelector('[data-search-input]').setAttribute('aria-activedescendant', next?.id ?? '');
}

document.addEventListener('keydown', (event) => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        open();

        return;
    }

    const dialog = event.target.closest?.('[data-search-dialog]');

    if (!dialog) {
        return;
    }

    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        move(dialog, event.key === 'ArrowDown' ? 1 : -1);
    } else if (event.key === 'Enter' && event.target.matches('[data-search-input]')) {
        event.preventDefault();
        dialog.querySelector('[role="option"][aria-selected="true"]')?.click();
    }
});

document.addEventListener('click', (event) => {
    if (event.target.closest('[data-search-open]')) {
        event.preventDefault();
        open();

        return;
    }

    // الضغط على الخلفية خارج النافذة يغلقها
    if (event.target.matches?.('[data-search-dialog]')) {
        event.target.close();
    }
});

document.addEventListener('input', (event) => {
    if (event.target.matches('[data-search-input]')) {
        render(event.target.closest('[data-search-dialog]'), event.target.value);
    }
});

onPage((signal) => {
    const dialog = document.querySelector('[data-search-dialog]');

    dialog?.addEventListener(
        'close',
        () => {
            opener?.focus?.();
            opener = null;
        },
        { signal },
    );
    // الاختيار ينقل إلى صفحته، فتُغلق النافذة قبل التنقّل
    dialog?.addEventListener('click', (event) => event.target.closest('[role="option"]') && dialog.close(), { signal });
});
