# خطة: المساعد الذكي (`feature/ai-assistant`)

المتطلبات: `requirements/10-ai-assistant.md` بإضافتي صفوان (19 أكتوبر 2026). والقرار في `decisions.md` (19 أكتوبر). ودليل التشغيل `guides/ai-assistant.md`.

## ما يُبنى

- **طبقة المزود** (`app/Contracts/AiAssistant.php`، `app/Services/Assistant/`):
  - الوهمي، وClaude (Messages API)، والمتوافق مع OpenAI (Chat Completions).
  - بعميل HTTP في لارافيل، ومهلة كلية بلا إعادة محاولة.
  - `AssistantProviders` يبنيه من الإعدادات، ومشتركة مع تحليل الإحصائيات لاحقاً.
- **الإعدادات** (`AssistantSettings`، شاشة «إعدادات المساعد»):
  - التشغيل، والتوليد، والمزود، والنموذج، والعنوان، والمفتاح مشفّراً.
  - والسقف وسعر الوحدات، والترحيب، والأسئلة المقترحة، ومدة الاحتفاظ.
  - و«اختبار الاتصال».
- **المحتوى المعتمد:**
  - «الأسئلة الشائعة» (`AssistantFaq`).
  - و«دليل استخدام البوابة» (`AssistantGuide`)، بمسوداته من الشيفرة (`database/data/assistant-guide.php` و`AssistantGuideSeeder`).
- **الاسترجاع** (`app/Support/Assistant/`):
  - `ArabicText` للتطبيع، و`AssistantIndex` لفهرس المنشور، و`AssistantRetriever` للبحث في PostgreSQL.
  - والفهرس يتبع النماذج (`AppServiceProvider`)، ويُعاد بناؤه بـ `ejaz:assistant-index`.
- **السؤال** (`AskAssistant`):
  - حذف البيانات الشخصية، ثم الوضع الاحتياطي بالدليل، أو المقاطع والمزود.
  - ثم السقف (`CheckAssistantBudget`)، والخانات المتزامنة (`ConcurrencyGate`).
  - ثم المصادر من الخادم (`AnswerComposer`)، والسجل.
- **المسارات:** `POST /assistant/ask` و`POST /assistant/answers/{uuid}/rating`، خلف `EnsureAssistantEnabled` وحد `assistant`.
- **السجل** (`AssistantLog`، شاشة «سجل المحادثات»):
  - بلا عنوان ولا مستخدم.
  - تعليم الإجابة الخاطئة، والحذف بـ `model:prune` يومياً.
- **الواجهة** (`partials/assistant.blade.php`، `resources/js/assistant.js`):
  - زر عائم، ونافذة حوار تبقى عبر التنقّل السريع (`@persist`).
  - قوالب `<template>` تُملأ نصاً.

## المطابقة البصرية

لا شاشة للمساعد في صور التصميم (`docs/design/`). فبُنيت النافذة باللغة البصرية العامة (`ui-reference.md`):

- رأس بالأزرق الأساسي.
- بطاقات بيضاء بزوايا مستديرة واسعة وظل ناعم.
- أزرار كبسولية، والرئيسي أزرق.
- أيقونات Heroicons خطية.

وعلى الجوال تفتح بعرض الشاشة. واللقطات في `docs/design/screenshots/assistant-{1440,820,390}.png`.

## التحقق

- **Pest** في `tests/Feature/Assistant/`:
  - شروط القبول كلها.
  - والصلاحيات والرفض، وعدم تسرب المفتاح، وعدم إرسال المسودات.
  - والحدود والمهلة والتزامن والسقف، والسجل بلا عنوان.
  - والوضع الاحتياطي، ومقالات الدليل مقابل المسارات والقوالب.
- **المتصفح:** `tests/browser/assistant.spec.mjs` على العروض الثلاثة بالمزود الوهمي.
- `composer check` و`npm run build`، ومراجعة أمنية مستقلة.
