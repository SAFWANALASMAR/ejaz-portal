<?php

namespace App\Services\OnlineMeetings;

use App\Contracts\OnlineMeetings;
use App\Models\Meeting;
use RuntimeException;

/**
 * للتطوير والاختبارات فقط: رابط تجريبي بمعرّف الاجتماع، ويحفظ ما طُلب منه. ويرفض العمل في الإنتاج، فلا
 * يُنسى الربط الحقيقي.
 */
final class FakeOnlineMeetings implements OnlineMeetings
{
    /** @var list<string> */
    public array $calls = [];

    public function create(Meeting $meeting): array
    {
        $this->record('create', $meeting);

        return ['id' => 'fake-'.$meeting->uuid, 'join_url' => 'https://teams.example.test/meet/'.$meeting->uuid];
    }

    public function update(Meeting $meeting): void
    {
        $this->record('update', $meeting);
    }

    public function cancel(Meeting $meeting): void
    {
        $this->record('cancel', $meeting);
    }

    private function record(string $call, Meeting $meeting): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('FakeOnlineMeetings must not be used in production.');
        }

        $this->calls[] = $call.':'.$meeting->uuid;
    }
}
