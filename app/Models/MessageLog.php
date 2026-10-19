<?php

namespace App\Models;

use App\Enums\MessageStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * حالة رسالة واحدة (أو محاولة إرسالها): القناة والمزود والاستعمال والحالة ورمز الخطأ. بلا نص الرسالة
 * (قد يكون رمز تحقق)، والجوال آخر أربعة أرقام وحدها. تُحذف بعد messaging.log_days (model:prune).
 *
 * @property MessageStatus $status
 */
#[Fillable(['channel', 'provider', 'usage', 'phone_hint', 'status', 'provider_message_id', 'error', 'attempt'])]
class MessageLog extends Model
{
    use MassPrunable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => MessageStatus::class];
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays((int) config('messaging.log_days')));
    }

    public static function hint(string $phone): string
    {
        return '***'.substr(preg_replace('/\D/', '', $phone) ?? '', -4);
    }
}
