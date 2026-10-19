<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * مزود قناة تواصل (sms أو whatsapp) وإعداداتها من اللوحة. المفاتيح في secrets مشفّرة بمفتاح التطبيق،
 * ومخفية من المصفوفات، ولا تدخل سجل العمليات (يُسجَّل تغييرها بلا قيمتها في UpdateMessagingChannels).
 * ولا ذاكرة مؤقتة: تُقرأ عند كل إرسال، فلا تُحفظ المفاتيح مفكوكة خارج القاعدة.
 *
 * @property string $channel
 * @property string|null $provider
 * @property array<string, mixed>|null $options
 * @property array<string, string>|null $secrets
 */
#[Fillable(['channel', 'provider', 'options', 'secrets'])]
#[Hidden(['secrets'])]
class MessagingChannel extends Model
{
    use LogsActivity;

    public const SMS = 'sms';

    public const WHATSAPP = 'whatsapp';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'secrets' => 'encrypted:array',
        ];
    }

    public static function for(string $channel): self
    {
        return static::query()->firstOrNew(['channel' => $channel]);
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return data_get($this->options ?? [], $key, $default);
    }

    /**
     * المفاتيح المحفوظة مفكوكة. وإن تعذّر فكها (تغيّر APP_KEY بلا APP_PREVIOUS_KEYS) تُعامل كأنها غير
     * مضبوطة، فلا يسقط الإرسال ولا الصفحة، ويستطيع المشرف إدخالها من جديد.
     *
     * @return array<string, string>
     */
    public function storedSecrets(): array
    {
        try {
            return (array) ($this->secrets ?? []);
        } catch (DecryptException) { // @phpstan-ignore catch.neverThrown (يرميه فك التشفير في encrypted:array، ولا يعلنه Eloquent)
            Log::critical('Messaging secrets cannot be decrypted; APP_KEY changed without APP_PREVIOUS_KEYS.', ['channel' => $this->channel]);

            // تُنسى القيمة التالفة في هذه النسخة وحدها، فيحل محلها المفتاح الجديد عند الحفظ (فحص التعديل
            // في Eloquent يفك القيمة الأصلية، فيسقط الحفظ بدونه)
            $this->setRawAttributes([...$this->getAttributes(), 'secrets' => null], true);

            return [];
        }
    }

    public function secret(string $key): ?string
    {
        $value = $this->storedSecrets()[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['channel', 'provider', 'options'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
