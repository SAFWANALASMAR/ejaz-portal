// شبكة النقاط في البطل (محور المدير «التقانة»): نقاط باهتة تضيء وتقترب من المؤشر حين يمر فوقها. على الحاسوب وحده (مؤشر
// دقيق)، ولا ترسم إلا حين يتحرك المؤشر داخل البطل وهو ظاهر، وتتوقف تماماً لمن طلب تقليل الحركة. canvas واحد بلا مكتبة.

import { onPage } from './page';

const GAP = 34;
const RADIUS = 150;

const motionOff = () => Number.parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--motion')) === 0;

onPage((signal) => {
    const canvas = document.querySelector('[data-hero-grid]');

    if (!canvas || motionOff() || !window.matchMedia('(pointer: fine)').matches) {
        return;
    }

    const hero = canvas.parentElement;
    const context = canvas.getContext('2d');
    let pointer = null;
    let frame = null;
    let visible = true;

    const resize = () => {
        const ratio = Math.min(window.devicePixelRatio || 1, 2);
        canvas.width = hero.clientWidth * ratio;
        canvas.height = hero.clientHeight * ratio;
        context.setTransform(ratio, 0, 0, ratio, 0, 0);
        draw();
    };

    function draw() {
        frame = null;
        context.clearRect(0, 0, hero.clientWidth, hero.clientHeight);

        if (!pointer || !visible) {
            return;
        }

        // النقاط القريبة من المؤشر وحدها تُرسم، فلا يُعاد رسم البطل كله
        const fromX = Math.max(0, Math.floor((pointer.x - RADIUS) / GAP)) * GAP;
        const fromY = Math.max(0, Math.floor((pointer.y - RADIUS) / GAP)) * GAP;

        for (let x = fromX; x <= pointer.x + RADIUS; x += GAP) {
            for (let y = fromY; y <= pointer.y + RADIUS; y += GAP) {
                const distance = Math.hypot(x - pointer.x, y - pointer.y);

                if (distance > RADIUS) {
                    continue;
                }

                const strength = 1 - distance / RADIUS;
                const pull = strength * 6;
                const dx = ((pointer.x - x) / (distance || 1)) * pull;
                const dy = ((pointer.y - y) / (distance || 1)) * pull;
                context.beginPath();
                context.fillStyle = `rgba(142, 197, 240, ${(0.12 + strength * 0.55).toFixed(3)})`;
                context.arc(x + dx, y + dy, 1.2 + strength * 1.6, 0, Math.PI * 2);
                context.fill();
            }
        }
    }

    hero.addEventListener(
        'pointermove',
        (event) => {
            if (event.pointerType !== 'mouse') {
                return;
            }
            const box = hero.getBoundingClientRect();
            pointer = { x: event.clientX - box.left, y: event.clientY - box.top };
            frame ??= requestAnimationFrame(draw);
        },
        { signal },
    );
    hero.addEventListener(
        'pointerleave',
        () => {
            pointer = null;
            frame ??= requestAnimationFrame(draw);
        },
        { signal },
    );

    const observer = new IntersectionObserver(([entry]) => {
        visible = entry.isIntersecting;
    });
    observer.observe(hero);
    const sizes = new ResizeObserver(resize);
    sizes.observe(hero);
    signal.addEventListener('abort', () => {
        observer.disconnect();
        sizes.disconnect();
        if (frame) {
            cancelAnimationFrame(frame);
        }
    });
});
