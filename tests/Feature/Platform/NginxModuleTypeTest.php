<?php

/**
 * عامل قارئ PDF في المكتبة وحدة .mjs (pdf.worker.min.mjs في public/build)، ولا يعرف امتدادها mime.types في صورة nginx،
 * فيصل application/octet-stream مع nosniff، والمتصفح يرفض السكربت الوحدة بغير نوع JavaScript، فلا يفتح كتاب. فيُضاف
 * النوع في سياق http قبل أي server: هناك يُلحق بأنواع mime.types، وفي server أو location يستبدلها كلها.
 * والتجربة الفعلية على صورة nginx في tests/nginx/run.sh (docs/reviews/2026-11-02-v0.15-perf.md).
 */
it('serves .mjs modules as JavaScript from the http context', function (string $path) {
    $config = (string) preg_replace('/^\s*#.*$/m', '', (string) file_get_contents(base_path($path)));
    $server = strpos($config, 'server {');

    expect(preg_match('/^types \{ application\/javascript mjs; \}$/m', $config, $types, PREG_OFFSET_CAPTURE))->toBe(1)
        ->and($server)->not->toBeFalse()
        ->and($types[0][1])->toBeLessThan((int) $server)
        // ولا types داخل كتلة، فلا تُستبدل أنواع الخادم كلها
        ->and(preg_match_all('/^[ \t]+types\s*\{/m', $config))->toBe(0);
})->with(['deploy/nginx/ejaz.conf', 'deploy/docker/nginx.conf']);

it('builds the PDF worker as an .mjs module, the file the nginx type is for', function () {
    $manifest = json_decode((string) file_get_contents(base_path('package-lock.json')), true);

    // pdfjs-dist يوزّع العامل .mjs وحده منذ الإصدار 4، وresources/js/pdf-reader.js يستورده بامتداده
    expect((int) explode('.', (string) ($manifest['packages']['node_modules/pdfjs-dist']['version'] ?? '0'))[0])->toBeGreaterThanOrEqual(4)
        ->and((string) file_get_contents(resource_path('js/pdf-reader.js')))->toContain('pdf.worker.min.mjs?url');
});
