// رحلة الاختبار الإلكتروني: تفعيل زر المتابعة حين تكتمل الحقول، والفيديو التعريفي (لا تقديم إلى الأمام،
// والزر بعد انتهائه)، ومنع تكرار الإرسال. وكل فحص هنا للراحة فقط، فالخادم يعيده كله (ExamLock).

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

// زر المتابعة معطّل حتى تصح الحقول الإلزامية كلها ومربع الموافقة
function gate(form) {
    const button = form.querySelector('[data-exam-gate-submit]');

    if (!button) {
        return;
    }

    const update = () => {
        button.disabled = !form.checkValidity();
    };

    form.addEventListener('input', update);
    form.addEventListener('change', update);
    update();
}

// الفيديو التعريفي: يُسجَّل بدء المشاهدة في الخادم، ويُمنع التقديم إلى ما بعد أبعد نقطة شوهدت،
// ويُفعَّل الزر عند انتهاء المقطع
function intro(video) {
    const button = document.querySelector('[data-exam-intro-next]');
    let watched = 0;
    let started = false;

    video.addEventListener('play', () => {
        if (started) {
            return;
        }

        // فشل التسجيل (انقطاع، أو رد 419 أو 429) يُعاد عند التشغيل التالي، والمشاهدة تبدأ من أولها
        // فلا يُرفض الانتهاء بعدها لبدء غير مسجل
        started = true;
        fetch(video.dataset.examIntro, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(String(response.status));
                }
            })
            .catch(() => {
                started = false;
                watched = 0;
                video.pause();
                video.currentTime = 0;
            });
    });

    video.addEventListener('timeupdate', () => {
        if (!video.seeking && video.currentTime > watched) {
            watched = video.currentTime;
        }
    });

    video.addEventListener('seeking', () => {
        if (video.currentTime > watched + 1) {
            video.currentTime = watched;
        }
    });

    video.addEventListener('ended', () => {
        if (button) {
            button.disabled = false;
            button.focus();
        }
    });
}

// زر الإرسال يُعطَّل بعد أول ضغطة، فلا يُرسل مرتين (والخادم متكرر الأمان أصلاً)
function once(form) {
    form.addEventListener('submit', () => {
        window.setTimeout(() => {
            form.querySelectorAll('button[type="submit"], button:not([type])').forEach((button) => {
                button.disabled = true;
            });
        }, 0);
    });
}

document.querySelectorAll('form[data-exam-gate]').forEach(gate);
document.querySelectorAll('video[data-exam-intro]').forEach(intro);
document.querySelectorAll('form[data-once]').forEach(once);
