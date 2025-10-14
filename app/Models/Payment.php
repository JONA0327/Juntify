<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_id',
        'subscription_id',
        'external_reference',
        'external_payment_id',
        'status',
        'amount',
        'currency',
        'payment_method',
        'payment_method_id',
        'payer_email',
        'payer_name',
        'description',
        'webhook_data',
        'processed_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'webhook_data' => 'array',
        'processed_at' => 'datetime'
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_AUTHORIZED = 'authorized';
    const STATUS_IN_PROCESS = 'in_process';
    const STATUS_IN_MEDIATION = 'in_mediation';
    const STATUS_REJECTED = 'rejected';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_CHARGED_BACK = 'charged_back';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function subscription()
    {
        return $this->belongsTo(UserSubscription::class);
    }

    public function isApproved()
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isPending()
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_IN_PROCESS, self::STATUS_AUTHORIZED]);
    }

    public function isRejected()
    {
        return in_array($this->status, [self::STATUS_REJECTED, self::STATUS_CANCELLED, self::STATUS_REFUNDED, self::STATUS_CHARGED_BACK]);
    }
}
