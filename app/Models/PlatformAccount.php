<?php

namespace App\Models;

use Database\Factories\PlatformAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * حساب الجمعية في منصة خارجية: الجهة أو المنصة، والمسؤول، واسم المستخدم، ورابط الدخول. بلا كلمة مرور
 * (قرار مسجّل في docs/decisions.md): كلمات المرور في مدير كلمات مرور خارج البوابة.
 */
#[Fillable(['platform', 'responsible_id', 'username', 'login_url', 'notes'])]
class PlatformAccount extends Model
{
    /** @use HasFactory<PlatformAccountFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * @return BelongsTo<User, $this>
     */
    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('registers')->logOnly(['platform', 'responsible_id', 'username', 'login_url'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
