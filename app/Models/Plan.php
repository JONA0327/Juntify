<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'code','name','description','price','currency','billing_cycle_days','is_active','features'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'billing_cycle_days' => 'integer',
        'is_active' => 'boolean',
        'features' => 'array',
    ];

    public function subscriptions()
    {
        return $this->hasMany(UserSubscription::class);
    }
}
