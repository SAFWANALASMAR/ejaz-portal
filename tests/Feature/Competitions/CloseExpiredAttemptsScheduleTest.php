<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

/*
| إغلاق المحاولات المنتهية كل دقيقة بقفل منع تداخل قصير: الافتراضي 24 ساعة، فإن أُعيد تشغيل الحاويات أثناء التشغيل بقي القفل
| معلقاً وتوقف التسليم الآلي يوماً كاملاً (وجدته جلسة رابط العرض، 4 نوفمبر 2026).
*/

it('closes expired attempts every minute with a five-minute overlap lock, not the default day', function () {
    $event = collect(app(Schedule::class)->events())->first(fn (Event $event): bool => str_contains((string) $event->command, 'ejaz:close-expired-attempts'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->expiresAt)->toBe(5)
        ->and($event->onOneServer)->toBeTrue();
});
