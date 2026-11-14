<?php

use App\Models\User;

// زر الخروج بأيقونة بجانب «حسابي» في الرأس وفي قائمة الجوال، لمن دخل وحده (decisions/2026-11-14-fix-header-logout-icon.md)
function headerXPath(string $html): DOMXPath
{
    $dom = new DOMDocument;
    @$dom->loadHTML($html);

    return new DOMXPath($dom);
}

it('shows a logout icon next to my account in the header and the mobile menu for a signed-in user', function () {
    $html = $this->actingAs(User::factory()->create())->get('/')->assertOk()->getContent();
    $x = headerXPath($html);

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

it('shows no logout button in the header to a guest', function () {
    $x = headerXPath($this->get('/')->assertOk()->getContent());

    expect($x->query("//header[@data-site-header]//form[@data-header-logout or @data-menu-logout]")->length)->toBe(0)
        ->and($x->query("//header[@data-site-header]//a[@href='".route('login')."']")->length)->toBeGreaterThan(0);
});

it('signs the user out from the header button', function () {
    $this->actingAs(User::factory()->create())->post(route('logout'))->assertRedirect();

    $this->assertGuest();
});
