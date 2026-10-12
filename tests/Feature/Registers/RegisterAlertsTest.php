<?php

use App\Actions\Registers\SaveRegisterEntry;
use App\Actions\Registers\SendRegisterAlerts;
use App\Enums\RegisterAlertThreshold;
use App\Enums\RoleName;
use App\Models\RegisterEntry;
use App\Notifications\RegisterEntryAlert;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/*
| تنبيه انتهاء المستندات (requirements/13 §1): مرة واحدة لكل عتبة (دخول مدة التنبيه، والانتهاء) بمهمة
| يومية، إشعاراً في اللوحة وبريداً للمسؤول، ولا يتكرر، ويتوقف للمستند المجدَّد.
*/

beforeEach(function () {
    Notification::fake();
    $this->responsible = userWithRole(RoleName::Secretary);
    $this->travelTo(Carbon::parse('2026-10-10 08:00:00', 'Asia/Riyadh'));
});

function alertEntry(array $attributes = []): RegisterEntry
{
    return RegisterEntry::factory()->create(['responsible_id' => test()->responsible->id, 'alert_days' => 30, ...$attributes]);
}

it('alerts the responsible once when the alert window opens, and does not repeat', function () {
    $entry = alertEntry(['expires_on' => '2026-11-09']);
    alertEntry(['expires_on' => '2026-11-10']); // قبل مدة التنبيه بيوم

    expect(app(SendRegisterAlerts::class)->handle())->toBe(1)
        ->and(app(SendRegisterAlerts::class)->handle())->toBe(0);

    Notification::assertSentToTimes($this->responsible, RegisterEntryAlert::class, 1);
    Notification::assertSentTo($this->responsible, RegisterEntryAlert::class, function (RegisterEntryAlert $alert, array $channels) use ($entry) {
        return $alert->entry->is($entry) && $alert->threshold === RegisterAlertThreshold::Expiring && $channels === ['database', 'mail'];
    });
});

it('alerts again once at expiry, then never for that date', function () {
    $entry = alertEntry(['expires_on' => '2026-10-20']);
    app(SendRegisterAlerts::class)->handle();

    $this->travelTo(Carbon::parse('2026-10-20 08:00:00', 'Asia/Riyadh')); // اليوم الأخير: ما زال قارب
    expect(app(SendRegisterAlerts::class)->handle())->toBe(0);

    $this->travelTo(Carbon::parse('2026-10-21 08:00:00', 'Asia/Riyadh'));
    expect(app(SendRegisterAlerts::class)->handle())->toBe(1);

    $this->travelTo(Carbon::parse('2026-11-30 08:00:00', 'Asia/Riyadh'));
    expect(app(SendRegisterAlerts::class)->handle())->toBe(0);

    Notification::assertSentToTimes($this->responsible, RegisterEntryAlert::class, 2);
    expect(DB::table('register_entry_alerts')->where('register_entry_id', $entry->id)->pluck('threshold')->sort()->values()->all())->toBe(['expired', 'expiring']);
});

it('sends only the expiry alert for an entry first seen already expired', function () {
    alertEntry(['expires_on' => '2026-09-01']);

    app(SendRegisterAlerts::class)->handle();

    Notification::assertSentTo($this->responsible, RegisterEntryAlert::class, fn (RegisterEntryAlert $alert) => $alert->threshold === RegisterAlertThreshold::Expired);
    Notification::assertSentToTimes($this->responsible, RegisterEntryAlert::class, 1);
});

it('stops alerting a renewed entry, and alerts its new date when that date comes', function () {
    $entry = alertEntry(['expires_on' => '2026-10-20']);
    app(SendRegisterAlerts::class)->handle();

    // التجديد: تاريخ انتهاء جديد بعد سنة
    app(SaveRegisterEntry::class)->handle(userWithRole(RoleName::ExecutiveDirector), $entry, [
        'type' => $entry->type->value, 'title' => $entry->title, 'issuer' => $entry->issuer, 'issued_on' => '2026-10-15',
        'expires_on' => '2027-10-20', 'alert_days' => 30, 'responsible_id' => $this->responsible->id, 'attachments' => [],
    ]);

    $this->travelTo(Carbon::parse('2026-10-25 08:00:00', 'Asia/Riyadh'));
    expect(app(SendRegisterAlerts::class)->handle())->toBe(0);

    $this->travelTo(Carbon::parse('2027-09-20 08:00:00', 'Asia/Riyadh'));
    expect(app(SendRegisterAlerts::class)->handle())->toBe(1);

    Notification::assertSentToTimes($this->responsible, RegisterEntryAlert::class, 2);
});

it('respects a per-entry alert window', function () {
    alertEntry(['expires_on' => '2026-12-01', 'alert_days' => 60]);
    alertEntry(['expires_on' => '2026-12-01', 'alert_days' => 10]);

    expect(app(SendRegisterAlerts::class)->handle())->toBe(1);
});

it('skips entries without a responsible, inactive responsibles, deleted entries and those without expiry', function () {
    alertEntry(['expires_on' => null]);
    RegisterEntry::factory()->create(['responsible_id' => null, 'expires_on' => '2026-10-12', 'alert_days' => 30]);
    alertEntry(['expires_on' => '2026-10-12'])->delete();
    $inactive = userWithRole(RoleName::Secretary, ['is_active' => false]);
    RegisterEntry::factory()->create(['responsible_id' => $inactive->id, 'expires_on' => '2026-10-12', 'alert_days' => 30]);
    // ومن فقد صلاحية الاطلاع
    $member = userWithRole(RoleName::Member);
    RegisterEntry::factory()->create(['responsible_id' => $member->id, 'expires_on' => '2026-10-12', 'alert_days' => 30]);

    expect(app(SendRegisterAlerts::class)->handle())->toBe(0);
    Notification::assertNothingSent();
});

it('runs from the daily scheduled command', function () {
    alertEntry(['expires_on' => '2026-10-15']);

    $this->artisan('registers:send-alerts')->assertSuccessful();
    $this->artisan('registers:send-alerts')->assertSuccessful();

    Notification::assertSentToTimes($this->responsible, RegisterEntryAlert::class, 1);
});

it('renders the mail and the panel notification in Arabic', function () {
    $entry = alertEntry(['expires_on' => '2026-10-15', 'title' => 'ترخيص الاختبار']);
    $alert = new RegisterEntryAlert($entry, RegisterAlertThreshold::Expiring);

    expect($alert->toMail($this->responsible)->subject)->toContain('ترخيص الاختبار')
        ->and($alert->toDatabase($this->responsible)['title'])->toContain('ترخيص الاختبار')
        ->and($alert->toDatabase($this->responsible)['format'])->toBe('filament');
});
