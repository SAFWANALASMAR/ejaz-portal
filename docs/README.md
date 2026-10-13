# فهرس المشروع — بوابة جمعية إعجاز

نقطة البدء لكل من يتسلم المشروع: أين كل وثيقة، وأين شيفرة كل وحدة وخطتها واختباراتها، وما حالتها. الحالة كما في `roadmap.md`. والإصدار وما تغيّر في `../CHANGELOG.md`.

## ابدأ هنا

- `../CLAUDE.md` — دستور المشروع وقواعده الذهبية.
- `overview.md` — الجمعية، والهدف، والمستخدمون، والمتطلبات الحاكمة.
- `architecture.md` — البنية وقواعد كتابة الشيفرة ومكان كل شيء.
- `workflow.md` — سير العمل من الفرع إلى الدمج، وكيف تسير الطلبات داخل البوابة.
- `roadmap.md` — المراحل وحالة كل بند.
- `HANDOFF.md` — حالة الفروع وأول خطوة للاستئناف.
- `../دليل-التشغيل.md` — تشغيل البيئة المحلية.

## المرجعيات

- **القرارات:** `decisions.md` (كل قرار معماري أو قرار صفوان بتاريخه وسببه).
- **الناقص من الجمعية:** `open-questions.md` (كل علامة `【ناقص: …】` لها بند هنا).
- **الأدوار والصلاحيات:** `roles-permissions.md`، والمصفوفة في `../config/permissions.php`.
- **الحماية:** `security.md` (إلزامية في كل ميزة)، وتقارير الفحص في `security/` (آخرها `2026-10-12-security-audit.md`).
- **الأداء والتحمل:** `performance.md`.
- **الهوية والواجهة:** `design-system.md`، والمرجع البصري في `design/`.
- **المحتوى الحالي ونقله:** `content-inventory.md`، وملف الاستيراد في `legacy-import/README.md`.
- **المتطلبات:** `requirements/01` إلى `requirements/13`، وملاحظات المدير التنفيذي في `requirements/12-executive-director-2026-10-10.md` ومدخلاتها في `inputs/`.
- **المراجعات:** `reviews/` (مراجعات الوكلاء، وملفات `ready-*` للفروع الجاهزة للدمج، وتقرير نقل الأرشيف).
- **سجل الجلسات:** `progress-log.md`.

## النشر والإطلاق (جاهز بانتظار الخادم)

- `deploy.md` — النشر خطوة خطوة.
- `deploy/readiness.md` — كل ما يلزم الإطلاق وأين هو في المستودع.
- `deploy/provider-checklist.md` — قائمة التحقق قبل اختيار المزوّد، والحد الأدنى للمواصفات.
- `deploy/staging-plan.md` — خطة الخادم التجريبي (التكلفة تنتظر موافقة صفوان).
- `launch-checklist.md`، و`launch-plan.md`، و`cutover.md` — يوم الإطلاق، ونقل النطاق، والتراجع.
- `monitoring.md` — ما يُراقب ومتى يُنبَّه.
- الملفات نفسها: `../deploy/` (compose الإنتاج، وnginx، وPHP، وRedis، والتخزين)، و`../scripts/` (النشر والنسخ الاحتياطي).

## الوحدات

لكل وحدة: ما تفعله، وأين شيفرتها، وخطتها، واختباراتها، وحالتها. منطق الأعمال في `app/Actions/<الوحدة>/`، والصلاحيات في `app/Policies/`، والنصوص في `lang/ar/`، وشاشات اللوحة في `app/Filament/Resources/`.

### الأساس والحسابات

- **التأسيس واللوحة:** العربية واتجاهها، وتوقيت الرياض، ولوحة `/admin` بالدخول الموحد والتحقق الثنائي، والقائمة، والإعدادات، والمستخدمون، وسجل العمليات.
  - الشيفرة: `app/Providers/Filament/AdminPanelProvider.php`، و`app/Actions/Users/`، و`app/Actions/Settings/`.
  - الخطة: `plans/admin-core.md`. الاختبارات: `tests/Feature/Admin/`، و`tests/Feature/Platform/`.
  - الحالة: مدموج.
- **الحسابات:** التسجيل، والدخول، والاستعادة، وتوثيق البريد والجوال، والتحقق الثنائي، وحقوق صاحب البيانات.
  - الشيفرة: `app/Actions/Fortify/`، و`app/Actions/Accounts/`، و`app/Http/Controllers/Account/`.
  - الاختبارات: `tests/Feature/Accounts/`، و`tests/Feature/Security/`.
  - الحالة: مدموج. وتوثيق الجوال ينتظر اعتماد مزود الرسائل.
- **البوابات الخمس:** مجلس الإدارة، والمدير التنفيذي، والموظفون، والمشتركون، والمحكّمون، ووجهة كل دور بعد الدخول.
  - الخطة: `plans/five-portals.md`. الاختبارات: `tests/Feature/Portals/`. الحالة: مدموج.
- **الهوية الرسمية:** الألوان، وخط Zain، والشعار، والتباين المختبر.
  - الخطة: `plans/brand-identity.md`. الحالة: مدموج.

### الموقع العام

- **الرئيسية:** `plans/home.md` و`plans/home-ed.md`. الحالة: مدموج.
- **عن الجمعية والمبادرات:** `plans/about-initiatives.md`. الشيفرة: `app/Actions/Content/`. الاختبارات: `tests/Feature/Content/`. الحالة: مدموج.
- **الحوكمة:** الوثائق بتصنيفاتها وعرضها وتنزيلها. `plans/governance.md`، والاختبارات `tests/Feature/Governance/`. الحالة: مدموج.
- **المكتبة:** المرئية والصوتية والكتب والأبحاث. `plans/library.md`، والاختبارات `tests/Feature/Library/`. الحالة: مدموج.
- **المركز الإعلامي:** الأخبار والصور والفيديو والشركاء. `plans/media.md`، والاختبارات `tests/Feature/Media/`. الحالة: مدموج.
- **التواصل والاستفسارات:** `plans/contact.md`، والاختبارات `tests/Feature/Contact/`. الحالة: مدموج.
- **خريطة الموقع وrobots:** `plans/seo-sitemap.md`. الحالة: مدموج.
- **نقل المحتوى وتحويل الروابط القديمة:** أمر `import:legacy-site`، وشاشة التحويلات، ومكتبة الوسائط.
  - الخطة: `plans/legacy-migration.md`، والملف `legacy-import/README.md`.
  - الحالة: الشيفرة والملف مدموجان. والنشر ينتظر اعتماد الجمعية للنصوص، وروابط المرئيات، وتواريخ الوثائق.

### الخدمات والبرامج

- **الطلبات الإلكترونية:** الانضمام للجمعية العمومية، والتوظيف، والتطوع، بحالاتها ومتابعتها.
  - الشيفرة: `app/Actions/Services/`، و`routes/services.php`. الخطة: `plans/e-services.md`. الاختبارات: `tests/Feature/Services/`. الحالة: مدموج.
- **البرامج والدورات:** التسجيل، والمقاعد، وقائمة الانتظار، والحضور، والشهادة.
  - الشيفرة: `app/Actions/Programs/`، و`routes/programs.php`. الخطة: `plans/programs.md`. الاختبارات: `tests/Feature/Programs/`.
  - الحالة: مدموج. وقالب الشهادة الرسمي وربط تيمز ينتظران الجمعية وقرار حساب Microsoft 365.
- **لوحة المستخدم:** طلباتي، وبرامجي، ونماذجي، ومواعيدي. `plans/user-dashboard.md`. الحالة: مدموج.

### المسابقات وقياس الرضا

- **المسابقات:** التسجيل، والأسئلة، ورفع الفيديو بأجزاء، والتصحيح الآلي، والتحكيم، والسحب.
  - الشيفرة: `app/Actions/Competitions/`، و`routes/competitions.php`. الخطة: `plans/competitions.md`.
- **رحلة الاختبار الإلكتروني ورفع الأعمال:** بنصوصها الحرفية، ورفع الصور وPDF والروابط، والفيديو التعريفي حتى 200 ميجابايت.
  - الخطة: `plans/competition-exam.md`. الاختبارات (للاثنين): `tests/Feature/Competitions/`.
  - الحالة: مدموج. ويبقى الفحص بـ ZAP واختبار التحمل على الخادم التجريبي قبل فتح التسجيل.
- **قياس الرضا والإحصائيات:** `app/Actions/Surveys/`، و`routes/surveys.php`. الخطة: `plans/surveys-stats.md`. الاختبارات: `tests/Feature/Surveys/`. الحالة: مدموج.

### الإدارة المؤسسية

- **الجمعية العمومية:** الأعضاء، والاجتماعات، والمحاضر، والملفات.
  - الشيفرة: `app/Actions/Assembly/`، و`routes/assembly.php`. الخطة: `plans/assembly.md`. الاختبارات: `tests/Feature/Assembly/`.
  - الحالة: مدموج. وربط تيمز الفعلي خلف واجهة (`app/Contracts/OnlineMeetings.php`) ينتظر قرار حساب Microsoft 365.
- **الملفات والشواهد والنماذج الإلكترونية والأرشيف:**
  - الشيفرة: `app/Actions/Documents/`، و`routes/documents.php`. الخطة: `plans/documents-forms.md`.
- **النماذج بشكل الورق:** الطباعة A4 بالترويسة، والحفظ في ملف الموظف أو المشارك، والتصدير.
  - الخطة: `plans/forms-print.md`. الاختبارات (للاثنين): `tests/Feature/Documents/`.
  - الحالة: مدموج. والنماذج الورقية الفعلية تنتظر الجمعية.
- **ملفات الوظائف:** لوحة لكل وظيفة من الست بملفاتها ومسؤوليها، وسجل لكل عملية.
  - الشيفرة: `app/Actions/JobFiles/`، و`routes/job-files.php`. الخطة: `plans/job-files.md`. الاختبارات: `tests/Feature/JobFiles/`. الحالة: مدموج.
- **السجلات والخطة السنوية وحسابات المنصات:** السجلات والمستندات ذات الصلاحية بتنبيهاتها، وسجلات اللجان والمجلس والجمعية، والخطة السنوية مربوطة بالبرامج، وحسابات المنصات بلا كلمات مرور.
  - الشيفرة: `app/Actions/Registers/`، و`routes/registers.php`. الخطة: `plans/registers.md`. الاختبارات: `tests/Feature/Registers/`. الحالة: مدموج.
- **لوحة إحصائيات الإدارة والمجلس:** `plans/admin-stats.md`. الحالة: مدموج.

### المتجر والخدمات المكملة

- **المتجر:** رابط خارجي إلى متجر الجمعية على سلة، من الإعدادات بنطاق مسموح.
  - الخطة: `plans/store-salla.md`. الحالة: مدموج.
  - المتجر الداخلي والدفع والتبرعات مؤرشفة خلف مفتاح معطّل: `plans/store-payments.md`، و`app/Actions/Payments/`، و`tests/Feature/Payments/`.
- **البث المباشر:** `plans/livestream.md`، والاختبارات `tests/Feature/Livestream/`. الحالة: مدموج.
- **المساعد الذكي:** `requirements/10-ai-assistant.md`. الحالة: لم يبدأ.

## التكاملات الخارجية

كل تكامل خلف واجهة في `app/Contracts/` بتطبيق وهمي للاختبارات. ولا يُربط مزود قبل قرار صفوان (`open-questions.md`):

- الرسائل النصية: `SmsGateway`.
- واتساب: `WhatsAppNotifier`.
- الاجتماعات (تيمز): `OnlineMeetings`.
- الدفع (مؤرشف): `PaymentGateway`.
- كتالوج المتجر (مؤرشف): `StoreCatalog`.
- رفع الفيديو: `VideoUploads`.
- التحدي البشري: `BotChallenge`.

## الجودة

- `composer check`: التنسيق (Pint)، والتحليل (Larastan)، والاختبارات (Pest).
- الفحص الآلي على GitHub مع كل رفع وطلب دمج: `../.github/workflows/ci.yml`.
