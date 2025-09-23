<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class UserPlan extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'plan_id',
        'role',
        'starts_at',
        'expires_at',
        'status',
        'has_unlimited_roles',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'has_unlimited_roles' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(PlanPurchase::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeCurrent(Builder $query): Builder
    {
        $now = Carbon::now();

        return $query
            ->active()
            ->where('starts_at', '<=', $now)
            ->where(function (Builder $builder) use ($now) {
                $builder->whereNull('expires_at')->orWhere('expires_at', '>=', $now);
            });
    }

    public function isUnlimited(): bool
    {
        return $this->has_unlimited_roles === true;
    }
}
