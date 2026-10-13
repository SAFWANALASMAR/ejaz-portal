<?php

namespace App\Models;

use Database\Factories\CommitteeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * لجنة من لجان الجمعية باسمها كما تُدخله الجمعية (requirements/13 §2). لا أسماء مبذورة.
 */
#[Fillable(['name', 'description'])]
class Committee extends Model
{
    /** @use HasFactory<CommitteeFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * @return HasMany<BodyMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(BodyMembership::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('registers')->logOnly(['name', 'description'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
