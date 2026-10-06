# بوابة جمعية إعجاز للدراسات والبحوث العلمية

إعادة بناء موقع ejaz.sa إلى بوابة خدمات متكاملة: حسابات، وصلاحيات، ولوحة إدارة، وحوكمة، ومكتبة، وبرامج، وجمعية عمومية، ومسابقات — بقدرة تحمل 30,000 مستخدم.

- **البدء والتشغيل:** [دليل-التشغيل.md](دليل-التشغيل.md)
- **دستور المشروع (لكلود كود):** [CLAUDE.md](CLAUDE.md)
- **المراحل والحالة:** [docs/roadmap.md](docs/roadmap.md)
- **المعلومات الناقصة من الجمعية:** [docs/open-questions.md](docs/open-questions.md)

## التقنيات

Laravel 13 · Filament 5 · PostgreSQL 17 · Redis 7 · Tailwind CSS 4 · Pest · Larastan · Docker

## التشغيل السريع

```bash
bash scripts/setup.sh          # أول مرة فقط
docker compose up -d           # كل يوم
```

ثم افتح:
- الموقع: http://localhost:8000
- لوحة الإدارة: http://localhost:8000/admin
- البريد التجريبي: http://localhost:8025
