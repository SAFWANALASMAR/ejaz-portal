<?php

use App\Models\Competition;
use App\Models\Initiative;
use App\Models\LibraryItem;
use App\Models\MenuItem;
use App\Models\NewsArticle;
use App\Models\Program;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

/**
 * الوصول (WCAG 2.1 AA) على الصفحات كما تُعرض: اللغة والاتجاه، والمعالم، ورابط التخطي، وعنوان h1 واحد
 * بترتيب عناوين سليم، والنص البديل للصور، وتسميات الحقول، وروابط aria-* بعناصر موجودة.
 * والفحص في المتصفح (axe-core) في tests/browser/a11y.spec.mjs. docs/reviews/2026-10-13-accessibility.md
 */
function a11yDom(TestResponse $response): DOMXPath
{
    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="utf-8"?>'.$response->getContent());
    libxml_clear_errors();

    return new DOMXPath($document);
}

/**
 * مخالفات القواعد العامة في الصفحة، فارغة إن سلمت.
 *
 * @return list<string>
 */
function a11yViolations(TestResponse $response): array
{
    $x = a11yDom($response);
    $problems = [];

    if ($x->query('//html[@lang="ar"][@dir="rtl"]')->length !== 1) {
        $problems[] = 'html lang/dir';
    }

    if ($x->query('//main[@id="main"]')->length !== 1) {
        $problems[] = 'main landmark';
    }

    // رابط التخطي أول رابط في الصفحة، ويشير إلى المحتوى
    $first = $x->query('//body//a[@href]')->item(0);
    if (! $first instanceof DOMElement || $first->getAttribute('href') !== '#main') {
        $problems[] = 'skip link';
    }

    $h1 = $x->query('//h1')->length;
    if ($h1 !== 1) {
        $problems[] = "h1 count {$h1}";
    }

    $previous = 0;
    foreach ($x->query('//h1|//h2|//h3|//h4|//h5|//h6') as $heading) {
        $level = (int) $heading->nodeName[1];
        if ($previous > 0 && $level > $previous + 1) {
            $problems[] = "heading skips h{$previous} to h{$level}";
        }
        $previous = $level;
    }

    foreach ($x->query('//img[not(@alt)]') as $image) {
        $problems[] = 'img without alt: '.$image->getAttribute('src');
    }

    foreach ($x->query('//input[not(@type="hidden") and not(@type="submit")]|//select|//textarea') as $control) {
        $id = $control->getAttribute('id');
        $labelled = $control->hasAttribute('aria-label') || $control->hasAttribute('aria-labelledby')
            || ($id !== '' && $x->query("//label[@for='{$id}']")->length > 0)
            || $x->query('ancestor::label', $control)->length > 0;

        // حقل المصيدة مخفي عن الجميع (aria-hidden على حاويته)
        if (! $labelled && $x->query('ancestor::*[@aria-hidden="true"]', $control)->length === 0) {
            $problems[] = 'unlabelled '.$control->nodeName.'['.$control->getAttribute('name').']';
        }
    }

    foreach ($x->query('//a[@href]|//button') as $control) {
        $named = trim($control->textContent) !== '' || $control->hasAttribute('aria-label') || $control->hasAttribute('title')
            || $x->query('.//img[@alt!=""]', $control)->length > 0;
        if (! $named) {
            $problems[] = 'unnamed '.$control->nodeName.' '.$control->getAttribute('href');
        }
    }

    foreach ($x->query('//*[@aria-describedby or @aria-controls or @aria-labelledby]') as $element) {
        $refs = $element->getAttribute('aria-describedby').' '.$element->getAttribute('aria-controls').' '.$element->getAttribute('aria-labelledby');
        foreach (array_filter(explode(' ', $refs)) as $ref) {
            if ($x->query("//*[@id='{$ref}']")->length === 0) {
                $problems[] = "aria reference to missing #{$ref}";
            }
        }
    }

    $ids = [];
    foreach ($x->query('//body//*[@id]') as $element) {
        $ids[] = $element->getAttribute('id');
    }
    foreach (array_keys(array_filter(array_count_values($ids), fn (int $count): bool => $count > 1)) as $id) {
        $problems[] = "duplicate id {$id}";
    }

    foreach ($x->query('//nav[not(@aria-label) and not(@aria-labelledby)]') as $nav) {
        $problems[] = 'nav without a name';
    }

    return array_values(array_unique($problems));
}

it('meets the common rules on every public page', function (string $path) {
    $response = $this->get($path)->assertOk();

    expect(a11yViolations($response))->toBe([]);
})->with([
    '/', '/initiatives', '/governance', '/library', '/library/books', '/media', '/media/news', '/media/albums',
    '/media/partners', '/contact', '/services', '/services/jobs', '/programs', '/competitions', '/assembly',
    '/login', '/register', '/forgot-password',
]);

it('meets the common rules on the detail pages', function () {
    Storage::fake('local');
    $initiative = Initiative::factory()->create();
    $news = NewsArticle::factory()->create();
    $book = LibraryItem::factory()->create();
    $program = Program::factory()->create();
    $competition = Competition::factory()->withQuestions()->create();

    foreach ([
        route('initiatives.show', $initiative->slug),
        route('media.news.show', $news->slug),
        route('library.show', [$book->section->value, $book->slug]),
        route('programs.show', $program->slug),
        route('competitions.show', $competition->slug),
    ] as $url) {
        expect(a11yViolations($this->get($url)->assertOk()))->toBe([], $url);
    }
});

it('keeps the error pages accessible', function () {
    expect(a11yViolations($this->get('/no-such-page')->assertNotFound()))->toBe([]);
});

it('meets the common rules on every page of the account area', function (string $path) {
    $user = User::factory()->create(['phone_verified_at' => now()]);

    expect(a11yViolations($this->actingAs($user)->get($path)->assertOk()))->toBe([]);
})->with(['/account', '/account/profile', '/account/data', '/account/requests', '/account/programs', '/account/competitions', '/account/forms']);

it('gives the account home a single h1, the welcome line', function () {
    $user = User::factory()->create(['name' => 'صاحب الحساب']);

    $x = a11yDom($this->actingAs($user)->get('/account')->assertOk());

    expect($x->query('//h1')->length)->toBe(1)
        ->and(trim($x->query('//h1')->item(0)->textContent))->toBe(__('account.dashboard.welcome', ['name' => 'صاحب الحساب']));
});

it('names the account menus and marks the current page in them', function () {
    $user = User::factory()->create();

    $x = a11yDom($this->actingAs($user)->get('/account/profile')->assertOk());
    $label = __('site.my_account');

    // الشريط الجانبي وقائمة الجوال، وكلاهما يعلن الصفحة الحالية
    expect($x->query("//nav[@aria-label='{$label}']")->length)->toBe(2)
        ->and($x->query("//nav[@aria-label='{$label}']//a[@aria-current='page'][@href='".route('account.profile')."']")->length)->toBe(2);
});

it('ties each label to its own field on the profile page, though two forms ask for the current password', function () {
    $user = User::factory()->create();

    $x = a11yDom($this->actingAs($user)->get('/account/profile')->assertOk());

    expect($x->query('//input[@name="current_password"]')->length)->toBe(2)
        ->and($x->query('//label[@for="profile-current-password"]')->length)->toBe(1)
        ->and($x->query('//input[@id="profile-current-password"][@aria-describedby="profile-current-password-hint"]')->length)->toBe(1)
        ->and($x->query('//label[@for="field-current_password"]')->length)->toBe(1);
});

it('links a field error to its input and marks required fields', function () {
    $this->from('/login')->post('/login', ['login' => '', 'password' => ''])->assertRedirect('/login');

    $x = a11yDom($this->get('/login')->assertOk());

    expect($x->query('//input[@id="field-login"][@aria-invalid="true"][@required]')->length)->toBe(1)
        ->and($x->query('//input[@id="field-login"]')->item(0)->getAttribute('aria-describedby'))->toContain('field-login-error')
        ->and($x->query('//*[@id="field-login-error"]')->length)->toBe(1)
        // النجمة للعين وحدها، والقارئ يعلن الحقل مطلوباً من الخاصية required
        ->and($x->query('//label[@for="field-login"]/span[@aria-hidden="true"]')->length)->toBe(1);
});

it('marks the active main-menu item as the current page on desktop and mobile, children included', function () {
    $parent = MenuItem::factory()->create(['label' => 'المركز الإعلامي', 'url' => '/media']);
    MenuItem::factory()->create(['parent_id' => $parent->id, 'label' => 'الأخبار', 'url' => '/media/news']);
    MenuItem::factory()->create(['label' => 'المكتبة', 'url' => '/library']);

    $x = a11yDom($this->get('/media/news')->assertOk());
    $label = __('site.main_navigation');

    // الرابط الفرعي الحالي في القائمتين، وزر القائمة الأم يفتح عنصراً موجوداً ويعلن حالته
    // والمكتبة قائمة منسدلة أيضاً بأقسامها منذ ملاحظات المدير في 1 نوفمبر 2026 (البند ٤)، فالأزرار أربعة في القائمتين
    expect($x->query("//nav[@aria-label='{$label}']//a[@aria-current='page'][normalize-space()='الأخبار']")->length)->toBe(2)
        ->and($x->query("//nav[@aria-label='{$label}']//a[@aria-current='page'][normalize-space()='المكتبة']")->length)->toBe(0)
        ->and($x->query("//nav[@aria-label='{$label}']//button[@aria-expanded='false'][@aria-controls]")->length)->toBe(4);

    $x = a11yDom($this->get('/library')->assertOk());

    expect($x->query("//nav[@aria-label='{$label}']//a[@aria-current='page'][normalize-space()='المكتبة']")->length)->toBe(2);
});

it('gives the skip link and the unavailable search icon an accessible name', function () {
    $x = a11yDom($this->get('/')->assertOk());

    expect(trim($x->query('//a[@href="#main"]')->item(0)->textContent))->toBe(__('site.skip_to_content'))
        ->and($x->query('//main[@id="main"][@tabindex="-1"]')->length)->toBe(1);

    // زر البحث السريع (Ctrl+K) في الرأس باسمه
    expect($x->query("//button[@data-search-open][@aria-label='".__('ui.search.open')."']")->length)->toBe(1);
});

it('describes each competition question group by its prompt', function () {
    Storage::fake('local');
    $competition = Competition::factory()->withQuestions()->create(['slug' => 'demo']);
    $applicant = User::factory()->create(['phone' => '+966500000001', 'phone_verified_at' => now()]);

    $this->actingAs($applicant)->post('/competitions/demo/entry/start');
    $x = a11yDom($this->get('/competitions/demo/entry')->assertOk());

    foreach ($competition->questions as $question) {
        $fieldset = $x->query("//fieldset[contains(@aria-describedby, 'answer-{$question->id}-prompt')]");

        expect($fieldset->length)->toBe(1)
            ->and($x->query("//*[@id='answer-{$question->id}-prompt']")->length)->toBe(1);
    }

    expect(a11yViolations($this->get('/competitions/demo/entry')))->toBe([]);
});
