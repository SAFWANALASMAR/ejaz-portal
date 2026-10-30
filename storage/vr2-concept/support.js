// بديل محلي مبسّط لـ support.js أداة التصميم، لرسم ملفات التصور النهائي في Playwright للمقارنة فقط (لا يُحفظ في git).
// يقرأ renderVals() ويوسّع sc-for وsc-if ويستبدل {{...}}.
class DCLogic {
    constructor(props) {
        this.props = props || {};
        this.state = {};
    }
    setState() {}
}
window.DCLogic = DCLogic;

document.addEventListener('DOMContentLoaded', () => {
    const script = document.querySelector('script[data-dc-script]');
    let vals = {};
    try {
        const Component = new Function('DCLogic', script.textContent + '\nreturn Component;')(DCLogic);
        vals = new Component({}).renderVals() || {};
    } catch (error) {
        console.error(error);
    }
    const root = document.querySelector('x-dc');
    const helmet = root.querySelector('helmet');
    if (helmet) {
        [...helmet.children].forEach((child) => document.head.append(child));
        helmet.remove();
    }

    const lookup = (path, scope) => {
        const [head, ...rest] = path.trim().split('.');
        let value = head in scope ? scope[head] : vals[head];
        for (const key of rest) value = value == null ? undefined : value[key];
        return value;
    };
    const fill = (text, scope) => text.replace(/\{\{\s*([^}]+?)\s*\}\}/g, (match, path) => {
        const value = lookup(path, scope);
        return typeof value === 'function' || value === undefined ? '' : String(value);
    });

    const walk = (node, scope) => {
        if (node.nodeType === Node.TEXT_NODE) {
            node.textContent = fill(node.textContent, scope);
            return;
        }
        if (node.nodeType !== Node.ELEMENT_NODE) return;
        const tag = node.tagName.toLowerCase();
        if (tag === 'sc-for') {
            const list = lookup((node.getAttribute('list') || '').replace(/[{}]/g, ''), scope) || [];
            const as = node.getAttribute('as');
            const fragment = document.createDocumentFragment();
            (Array.isArray(list) ? list : []).forEach((item, index) => {
                [...node.childNodes].forEach((child) => {
                    const copy = child.cloneNode(true);
                    const wrapper = document.createElement('div');
                    wrapper.append(copy);
                    walk(copy, { ...scope, [as]: item, index });
                    fragment.append(...wrapper.childNodes);
                });
            });
            node.replaceWith(fragment);
            return;
        }
        if (tag === 'sc-if') {
            const value = lookup((node.getAttribute('value') || '').replace(/[{}]/g, ''), scope);
            if (!value) {
                node.remove();
                return;
            }
            const children = [...node.childNodes];
            node.replaceWith(...children);
            children.forEach((child) => walk(child, scope));
            return;
        }
        [...node.attributes].forEach((attribute) => {
            if (attribute.value.includes('{{')) node.setAttribute(attribute.name, fill(attribute.value, scope));
        });
        [...node.childNodes].forEach((child) => walk(child, scope));
    };
    [...root.childNodes].forEach((child) => walk(child, {}));
    document.body.setAttribute('data-dc-ready', '');
});
