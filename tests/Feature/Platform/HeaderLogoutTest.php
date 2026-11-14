<?php

use App\Models\User;
use Illuminate\Testing\TestResponse;

// زر الخروج بأيقونة بجانب «حسابي» في الرأس وفي قائمة الجوال، لمن دخل وحده (decisions/2026-11-14-fix-header-logout-icon.md)
$headerDom = function (TestResponse $response): DOMXPath {
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8"?>'.$response->getContent());

    return new DOMXPath($document);
};

it('shows a logout icon next to my account in the header and the mobile menu for a signed-in user', function () use ($headerDom) {
    $x = $headerDom($this->actingAs(User::factory()->create())->get('/')->assertOk());

    foreach (['data-header-logout', 'data-menu-logout'] as $marker) {
        $forms = $x->query("//header[@data-site-header]//div[contains(@class, 'header-account')]/form[@{$marker}]");
        expect($forms->length)->toBe(1);

        $form = $forms->item(0);
        expect(strtoupper($form->getAttribute('method')))->toBe('POST')
            ->and($form->getAttribute('action'))->toBe(route('logout'))
            ->and($x->query(".//input[@name='_token']", $form)->length)->toBe(1)
            ->and($x->query(".//button[@type='submit'][@aria-label='".__('site.logout')."']", $form)->length)->toBe(1)
            ->and($x->query('preceding-sibling::a', $form)->item(0)?->getAttribute('href'))->toBe(route('account.dashboard'));
    }
});

it('shows no logout button in the header to a guest', function () use ($headerDom) {
    $x = $headerDom($this->get('/')->assertOk());

    expect($x->query('//header[@data-site-header]//form[@data-header-logout or @data-menu-logout]')->length)->toBe(0)
        ->and($x->query("//header[@data-site-header]//a[@href='".route('login')."']")->length)->toBeGreaterThan(0);
});
