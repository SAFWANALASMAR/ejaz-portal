<?php

use App\Enums\RoleName;
use App\Http\Middleware\AdminIdleTimeout;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

/**
 * لقطة أول مكوّن Livewire في صفحة اللوحة، ليُرسل إليه طلب كما يرسله المتصفح.
 */
function adminComponentSnapshot(mixed $test): string
{
    $html = (string) $test->get('/admin')->assertOk()->getContent();
    preg_match('/wire:snapshot="([^"]+)"/', $html, $match);
    expect($match)->not->toBeEmpty();

    return html_entity_decode($match[1], ENT_QUOTES);
}

/**
 * الاستطلاع الآلي (wire:poll) يحمل metadata.type = poll، والفعل الحقيقي بلا هذا الوصف.
 *
 * @param  array<string, mixed>  $metadata
 * @param  array<string, mixed>  $updates
 */
function adminLivewireCall(mixed $test, string $snapshot, array $metadata, array $updates = []): TestResponse
{
    // كل طلب في الخادم يبدأ بحالة Livewire نظيفة، وفي الاختبار تبقى بين الطلبات فلا تُعاد الوسائط الدائمة
    Livewire::flushState();

    return $test->withHeaders(['X-Livewire' => 'true'])->postJson(Livewire::getUpdateUri(), [
        '_token' => csrf_token(),
        'components' => [[
            'snapshot' => $snapshot,
            'updates' => $updates,
            'calls' => [['method' => '$refresh', 'params' => [], 'metadata' => $metadata]],
        ]],
    ]);
}

it('does not count automatic Livewire polling as admin activity', function () {
    $this->freezeSecond();
    $this->actingAs(userWithRole(RoleName::SuperAdmin));
    $snapshot = adminComponentSnapshot($this);
    $openedAt = now()->getTimestamp();

    $this->travel(25)->minutes();
    adminLivewireCall($this, $snapshot, ['type' => 'poll'])->assertOk();
    expect(session(AdminIdleTimeout::SESSION_KEY))->toBe($openedAt);

    // الاستطلاع كل دقيقة لا يُبقي الجلسة حية بعد مدة الخمول
    $this->travel(6)->minutes();
    adminLivewireCall($this, $snapshot, ['type' => 'poll']);
    $this->assertGuest();
    $this->get('/admin')->assertRedirect(route('login'));
});

it('counts a real Livewire action as admin activity', function () {
    $this->freezeSecond();
    $this->actingAs(userWithRole(RoleName::SuperAdmin));
    $snapshot = adminComponentSnapshot($this);

    $this->travel(25)->minutes();
    adminLivewireCall($this, $snapshot, [])->assertOk();
    expect(session(AdminIdleTimeout::SESSION_KEY))->toBe(now()->getTimestamp());

    $this->travel(6)->minutes();
    $this->get('/admin')->assertOk();
});

it('logs administrators out at the absolute session limit despite activity', function () {
    $this->freezeSecond();
    $this->actingAs(userWithRole(RoleName::SuperAdmin));
    $this->get('/admin')->assertOk();

    // نشاط متواصل كل عشرين دقيقة حتى السقف المطلق
    $limit = (int) config('security.admin_session_max_minutes');
    for ($elapsed = 20; $elapsed < $limit; $elapsed += 20) {
        $this->travel(20)->minutes();
        $this->get('/admin')->assertOk();
    }

    $this->travel(21)->minutes();
    $this->get('/admin')
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', __('auth.admin_session_expired'));
    $this->assertGuest();
});

it('starts the absolute limit at the first admin request of the session', function () {
    $this->actingAs(userWithRole(RoleName::SuperAdmin))
        ->withSession([AdminIdleTimeout::STARTED_KEY => now()->subMinutes((int) config('security.admin_session_max_minutes') + 1)->getTimestamp()])
        ->get('/admin')
        ->assertRedirect(route('login'));

    $this->assertGuest();
});
