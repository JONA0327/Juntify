<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanPurchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'user_plan_id',
        'provider',
        'payment_id',
        'external_reference',
        'status',
        'amount',
        'currency',
        'paid_at',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'float',
        'metadata' => 'array',
        'paid_at' => 'datetime',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(UserPlan::class, 'user_plan_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
