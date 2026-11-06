<?php

/*
| حارس انتقال المستندات (قبول ملاحظات 4 نوفمبر، 5 نوفمبر 2026 — partials/view-transition-guard.blade.php): سكربت مضمّن في الرأس برمز
| الطلب نفسه الذي في سياسة المحتوى، قبل سكربتات الصفحة، فيسبق كشفها ويلتقط رفض «Transition was aborted» وحده.
*/

it('puts the view transition guard first in the head, with the request nonce, before the page scripts', function () {
    $response = $this->get('/')->assertOk();
    $html = (string) $response->getContent();
    $head = (string) str($html)->before('</head>');

    expect(preg_match('#<script nonce="([^"]+)" data-navigate-once data-view-transition-guard>#', $head, $nonce))->toBe(1)
        ->and((string) $response->headers->get('Content-Security-Policy'))->toContain("'nonce-".$nonce[1]."'")
        ->and(strpos($head, 'data-view-transition-guard'))->toBeLessThan((int) strpos($head, '<script src='))
        ->and($head)->toContain("reason.name === 'InvalidStateError'")
        ->toContain("startsWith('Transition was aborted')")
        ->toContain("addEventListener('pagereveal'");
});
