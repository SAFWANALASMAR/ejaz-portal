<?php

// بديلا فك JPEG 2000 وJBIG2 (وCCITT عبره) بلا WebAssembly في قارئ الكتب: عامل pdf.js يطلبهما باسميهما الثابتين من مجلد wasmUrl، فإن
// عادا إلى اسم ببصمة (ترقية لـ Vite أو تعديل assetFileNames) طلب العامل اسماً غير موجود، ورُسمت الصفحات بلا صورها بلا أي إشارة
// (docs/decisions/2026-11-13-fix-reader-jpeg2000.md). والفحص الآلي يبني الأصول قبل الاختبارات، كما في AdminThemeTest.

it('builds the pdf.js no-wasm fallbacks unhashed in one folder named after the library version', function () {
    $manifest = json_decode((string) file_get_contents(public_path('build/manifest.json')), true);
    $version = json_decode((string) file_get_contents(base_path('node_modules/pdfjs-dist/package.json')), true)['version'];

    foreach (['openjpeg', 'jbig2'] as $name) {
        $file = $manifest["node_modules/pdfjs-dist/wasm/{$name}_nowasm_fallback.js"]['file'] ?? null;

        expect($file)->toBe("assets/pdfjs-{$version}/{$name}_nowasm_fallback.js")
            ->and(public_path('build/'.$file))->toBeFile();
    }
});
