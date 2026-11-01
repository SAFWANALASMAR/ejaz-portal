<?php

use App\Enums\RegistrationStatus;
use App\Models\Program;
use App\Models\ProgramRegistration;
use App\Models\ProgramType;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

/*
| «كيف تسجّل» (ملاحظة المدير التنفيذي، 29 أكتوبر 2026): الخطوات الخمس في «الدورات والبرامج» وصفحة البرنامج، وزر الخطوة
| الأولى بحسب حال الزائر، والنصوص من lang/ar/programs.php.
*/

beforeEach(function () {
    Notification::fake();
    $type = ProgramType::factory()->create(['name' => 'تدريبية', 'slug' => 'training']);
    $this->program = Program::factory()->for($type, 'type')->create(['slug' => 'research-skills', 'title' => 'مهارات البحث']);
});

/**
 * عناوين الخطوات الخمس بنصها من ملف الترجمة، بأسماء الأزرار التي تشير إليها.
 *
 * @return list<string>
 */
function howToStepTitles(): array
{
    $replace = ['button' => __('programs.show.register'), 'waitlist' => __('programs.show.join_waitlist'), 'login' => __('programs.show.login_to_register'), 'confirm' => __('programs.register.confirm'), 'mine' => __('programs.mine.title')];

    return array_map(fn (string $key): string => __('programs.how_to.steps.'.$key.'.title', $replace), ['account', 'choose', 'press', 'details', 'confirm']);
}

it('shows the five steps in order on the programs index and on a program page', function (string $path) {
    $section = howToSection($this->get($path)->assertOk());

    expect($section)->toContain(e(__('programs.how_to.steps_label')))->toContain(e(__('programs.how_to.title')));

    // الخطوات الخمس بترتيبها داخل القائمة المرقمة
    preg_match('/<ol\b[^>]*>(.*?)<\/ol>/s', $section, $list);
    $steps = $list[1] ?? '';
    expect(substr_count($steps, '<li'))->toBe(5);
    $positions = array_map(fn (string $title): int|false => mb_strpos($steps, e($title)), howToStepTitles());
    expect($positions)->not->toContain(false)
        ->and($positions)->toBe(collect($positions)->sort()->values()->all());

    // أسماء الأزرار في الخطوات هي أسماؤها في الصفحات نفسها
    expect($section)->toContain('«'.e(__('programs.show.register')).'»')
        ->toContain('«'.e(__('programs.register.confirm')).'»')
        ->toContain('«'.e(__('programs.mine.title')).'»');
})->with(['index' => '/programs', 'program page' => '/programs/research-skills']);

it('mentions verification only when the account settings require it', function () {
    $this->get('/programs')->assertDontSee(__('programs.how_to.steps.account.verify'));

    enableVerification();
    $this->get('/programs')->assertSee(__('programs.how_to.steps.account.verify'));
});

/**
 * قسم «كيف تسجّل» وحده من الصفحة، فلا تطابق التأكيدات نصاً في بطاقة البرنامج أو الرأس.
 */
function howToSection(TestResponse $response): string
{
    preg_match('/<section data-programs-how-to\b.*?<\/section>/s', (string) $response->getContent(), $match);

    return $match[0] ?? '';
}

/**
 * روابط أزرار الخطوة الأولى في القسم (data-how-to-start)، بترتيبها.
 *
 * @return list<string>
 */
function howToStartLinks(TestResponse $response): array
{
    preg_match_all('/<a href="([^"]+)"[^>]*data-how-to-start/', howToSection($response), $matches);

    return array_map(fn (string $href): string => html_entity_decode($href), $matches[1]);
}

it('sends a guest on a program page to log in or create an account, then back to the program registration', function () {
    $register = route('programs.register', 'research-skills');
    $signup = route('programs.register', ['slug' => 'research-skills', 'signup' => 1]);

    $response = $this->get('/programs/research-skills')->assertOk();
    expect(howToSection($response))->toContain(e(__('programs.how_to.login')))->toContain(e(__('programs.how_to.create_account')))
        ->and(howToStartLinks($response))->toBe([$register, $signup]);

    // صفحة التسجيل تطلب الدخول وتحفظ وجهتها، وsignup=1 إلى إنشاء الحساب بدل الدخول
    $this->get($register)->assertRedirect(route('login'));
    expect(session('url.intended'))->toBe($register);

    $this->get($signup)->assertRedirect(route('register'));
    expect(session('url.intended'))->toBe($signup);
});

it('sends a guest on the index to log in or create an account', function () {
    expect(howToStartLinks($this->get('/programs')->assertOk()))->toBe([route('login'), route('register')]);
});

it('points a signed-in visitor to the step that applies', function () {
    $user = User::factory()->create();

    // في «الدورات والبرامج»: اختيار برنامج من القائمة
    $index = $this->actingAs($user)->get('/programs')->assertOk()
        ->assertSee('id="programs-list"', false)
        ->assertSee(__('programs.how_to.browse'));
    expect(howToStartLinks($index))->toBe(['#programs-list']);

    // وفي صفحة برنامج مفتوح لم يسجل فيه: «سجّل الآن»
    expect(howToStartLinks($this->actingAs($user)->get('/programs/research-skills')->assertOk()))
        ->toBe([route('programs.register', 'research-skills')]);
});

it('shows a registered visitor their status and their programs instead of the register button', function () {
    $user = User::factory()->create();
    ProgramRegistration::factory()->for($this->program)->for($user)->create(['status' => RegistrationStatus::Confirmed]);

    $response = $this->actingAs($user)->get('/programs/research-skills')->assertOk();
    expect(howToSection($response))->toContain(e(__('programs.how_to.registered', ['status' => RegistrationStatus::Confirmed->getLabel()])))
        ->and(howToStartLinks($response))->toBe([route('account.programs.index')]);
});

it('offers the waitlist when seats are full and other programs when registration is closed', function () {
    $user = User::factory()->create();
    $this->program->update(['seats' => 1, 'has_waitlist' => true]);
    ProgramRegistration::factory()->for($this->program)->create(['status' => RegistrationStatus::Confirmed]);

    $waitlist = $this->actingAs($user)->get('/programs/research-skills')->assertOk();
    expect(howToSection($waitlist))->toContain(e(__('programs.show.join_waitlist')))
        ->and(howToStartLinks($waitlist))->toBe([route('programs.register', 'research-skills')]);

    $this->program->update(['registration_closes_at' => now()->subHour()]);
    $closed = $this->actingAs($user)->get('/programs/research-skills')->assertOk();
    expect(howToSection($closed))->toContain(e(__('programs.show.closed')))
        ->and(howToStartLinks($closed))->toBe([route('programs.index')]);
});

it('does not invite a guest to log in for a program that is not open', function (array $state, bool $fill) {
    $this->program->update($state);

    if ($fill) {
        ProgramRegistration::factory()->for($this->program)->create(['status' => RegistrationStatus::Confirmed]);
    }

    $response = $this->get('/programs/research-skills')->assertOk();
    expect(howToSection($response))->toContain(e(__('programs.show.closed')))
        ->and(howToStartLinks($response))->toBe([route('programs.index')]);
})->with([
    'soon' => [['registration_opens_at' => now()->addDay()], false],
    'full without a waitlist' => [['seats' => 1, 'has_waitlist' => false], true],
    'ended' => [['starts_at' => now()->subDays(2), 'ends_at' => now()->subDay()], false],
]);
