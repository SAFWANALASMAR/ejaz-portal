<?php

namespace App\Models;

use App\Enums\AccountCardAudience;
use App\Enums\SiteIcon;
use App\Rules\SafeUrl;
use Database\Factories\AccountCardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * بطاقة في رئيسية لوحة المستفيد (ملاحظات المدير التنفيذي، 25 أكتوبر 2026): قسم مبني باسم مساره (route)، أو رابط تضيفه
 * الإدارة (url). تُدار من اللوحة (AccountCardResource)، وتُعرض بـ AccountNavigation::cards.
 *
 * @property AccountCardAudience $audience
 * @property SiteIcon $icon
 */
#[Fillable(['title', 'description', 'icon', 'url', 'audience', 'sort', 'is_visible'])]
class AccountCard extends Model
{
    /** @use HasFactory<AccountCardFactory> */
    use HasFactory, LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'icon' => SiteIcon::class,
            'audience' => AccountCardAudience::class,
            'sort' => 'integer',
            'is_visible' => 'boolean',
        ];
    }

    /**
     * رابط البطاقة: مسار القسم المبني، وإلا رابطها الآمن، وإلا لا شيء (فلا تظهر).
     */
    public function href(): ?string
    {
        if ($this->route !== null) {
            return Route::has($this->route) ? route($this->route) : null;
        }

        return is_string($this->url) && SafeUrl::isSafe($this->url) ? $this->url : null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
