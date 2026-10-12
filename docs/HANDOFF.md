# التسليم والاستئناف — `preview/local`

آخر تحديث: 12 أكتوبر 2026. يحدّثه «الدامج» بعد كل دمج.

## تقسيم العمل (مهمة صفوان 12/10)

- **الدامج** (جلسة «ejaz-portal مستودع وفروعه»): `feature/forms-print`، ووحده يدمج في `preview/local`، ثم الفحص الأمني الشامل والمراجعة النهائية والفهرس والإصدار.
- **جلسة الاستئناف:** `feature/registers` ثم `feature/competition-exam`.
- **جلسة النقل:** `feature/legacy-content` ثم `chore/launch-readiness`.
- الفرع الجاهز يحمل `docs/reviews/ready-<اسم-الفرع>.md`. الدامج يدمج `preview/local` فيه أولاً، ثم يدمجه في `preview/local`، ثم يشغّل `composer check`، ويحدّث هذا الملف و`roadmap.md`، ويرفع. فرعاً واحداً في كل مرة.
- ممنوع: الدمج في `main`، والرفع القسري، وحذف الفروع، وتعديل ترحيل مدموج، والأسرار في git، واختراع المحتوى.

## حالة الفروع

- `feature/brand-identity`، `feature/home-ed`: مدموجان.
- `feature/five-portals`: مدموج.
- `feature/job-files`: مدموج (e030698).
- `feature/forms-print`: مدموج (fff53f6). المراجعتان في `plans/forms-print.md`. وأُصلح اختبار افترض المدير التنفيذي خارج اللوحة، ودخلها بعد البوابات الخمس.
- `feature/legacy-content`: مدموج (661d566). بعده على أي قاعدة: `migrate` ثم `PermissionsSeeder` (`media_assets.*`).
- `feature/registers`: مدموج (8fa68e4). المدير التنفيذي يرى الاجتماعات، وجهة الاجتماع تُختار ولا تُفترض.
- `feature/competition-exam`: مدموج بنسخة 424ebee. **تليها نسخة نهائية** بحدود رفع أعلى (200 ميغابايت، وقرص الرفع المؤقت لـ Livewire من البيئة)، تُدمج حين يصل ملف ready الجديد. وبعدها `npm run build` لتغيّر `resources/js/competition-exam.js`.
- `chore/launch-readiness`: عند جلسة النقل. لم يُدمج. حزمة النشر المستقلة عن المزود «جاهز بانتظار الخادم».

نتائج الاختبار المحلية بعد كل دمج: كلها ناجحة عدا اختبار الرابط الرمزي المعروف على ويندوز.

## ما بقي بالترتيب

1. دمج النسخة النهائية لرحلة الاختبار، ثم `chore/launch-readiness`، حين يظهر ملف ready على كل منهما.
2. `/security-audit` الشامل وتقريره في `docs/security/`، بلا ثغرة حرجة أو عالية.
3. مراجعة نهائية وتنظيف المكرر.
4. `docs/README.md` فهرساً شاملاً، و`CHANGELOG.md`، ووسم إصدار على `preview/local`.

## أول خطوة للاستئناف

```bash
git fetch origin
git log --oneline preview/local..origin/feature/competition-exam   # نسخة الاختبار النهائية إن وصلت
git ls-tree -r --name-only origin/chore/launch-readiness docs/reviews | grep ready-chore-launch-readiness
```

## ملاحظات البيئة المحلية (ويندوز بلا Docker)

- PHP 8.4 وPostgreSQL 17 من scoop. لكل جلسة قاعدة اختبار خاصة (`DB_DATABASE=ejaz_testing_N php artisan test`).
- مجلدات `storage` قد تصير للقراءة فقط بعد النسخ: `attrib -R storage\* /S /D` (وإلا فشلت اختبارات mPDF).
- اختبار الرابط الرمزي في `LegacyImportTest` يفشل على ويندوز وحده، وينجح في الفحص الآلي.
