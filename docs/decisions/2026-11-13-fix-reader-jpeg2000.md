# صور JPEG 2000 وJBIG2 في قارئ الكتب — 13 نوفمبر 2026

**المصدر:** رفع المشرف مواد «موقع الشيخ» إلى المكتبة (12 نوفمبر 2026): 49 مادة فيها صور مضغوطة بـ JPEG 2000 (`JPXDecode`) تظهر صفحاتها في القارئ بلا صورها. الفرع `fix/reader-jpeg2000`.

## السبب

- القارئ (`resources/js/pdf-reader.js`) يفتح الملفات بـ `useWasm: false` لئلا تتسع سياسة أمان المحتوى (قرار 30 أكتوبر 2026).
- وpdf.js 6.4.299 يفك JPEG 2000 وJBIG2 بـ OpenJPEG وJBIG2 مترجمين إلى WebAssembly، ومع `useWasm: false` يطلب بديلهما المكتوب بـ JavaScript (`openjpeg_nowasm_fallback.js` و`jbig2_nowasm_fallback.js`) من مجلد `wasmUrl`.
- و`wasmUrl` لم يُمرَّر، فيطلب العامل `nullopenjpeg_nowasm_fallback.js` ويفشل: «OpenJPEG failed to initialize»، وتُرسم الصفحة بلا الصورة.

## ما تقرر

- **`wasmUrl` إلى مجلد البديلين، ويبقى `useWasm: false`:** فلا WebAssembly ولا اتساع في السياسة، والبديلان JavaScript عادي بلا `eval` ولا `new Function`.
- **الملفان من حزمة pdfjs-dist نفسها** باستيراد `?url` في `pdf-reader.js`، لا نسخة في المستودع، فيتبعان إصدار المكتبة.
- **بلا بصمة في اسميهما:** pdf.js يطلبهما باسميهما الثابتين من مجلد واحد. فـ `vite.config.js` يضعهما في `build/assets/pdfjs-<الإصدار>/` (`assetFileNames`)، والمجلد باسم إصدار المكتبة فيتغير مع كل ترقية، ولا يبقى ملف قديم في تخزين `/build/` الطويل (سنة، `immutable`). وبقية الأصول بالنمط الافتراضي `assets/[name]-[hash][extname]`.
- **في خادم التطوير** يأتيان من `node_modules/pdfjs-dist/wasm/` في المجلد نفسه.
- لا مكتبة جديدة ولا تغيير في الخادم ولا في nginx: الملفان تحت `/build/` كبقية الأصول.

## التحقق

- `vite build` يُخرج `build/assets/pdfjs-6.4.299/openjpeg_nowasm_fallback.js` و`jbig2_nowasm_fallback.js`، والشيفرة المبنية تشير إليهما.
- صفحة اختبار بـ pdf.js المبني نفسه وخياراته، على ملف PDF فيه صورة JPEG 2000 بلونين في Chromium:
  - بلا `wasmUrl` (الحال على الموقع): الصفحة بيضاء، وفي العامل «OpenJPEG failed to initialize».
  - بـ `wasmUrl`: اللونان في موضعيهما.
- يبقى: فتح إحدى المواد الـ49 في القارئ على الخادم بعد النشر (مخفية الآن، فيُجرَّب من حساب الإدارة أو بإظهار مادة مؤقتاً).

## للنشر

- `npm run build` وحده، ولا ترحيل ولا بذرة.
