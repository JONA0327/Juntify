<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'price',
        'monthly_price',
        'yearly_price',
        'discount_percentage',
        'free_months',
        'currency',
        'billing_cycle_days',
        'is_active',
        'features'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'monthly_price' => 'decimal:2',
        'yearly_price' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'free_months' => 'integer',
        'billing_cycle_days' => 'integer',
        'is_active' => 'boolean',
        'features' => 'array',
    ];

    public function getMonthlyPrice(): float
    {
        return (float) ($this->monthly_price ?? $this->price ?? 0);
    }

    public function getBaseYearlyPrice(): float
    {
        $monthly = $this->getMonthlyPrice();
        return (float) ($this->yearly_price ?? ($monthly * 12));
    }

    public function getYearlyPriceWithOffers(): float
    {
        $price = $this->getBaseYearlyPrice();

        if (is_null($this->yearly_price) && $this->free_months > 0) {
            $price = $this->getMonthlyPrice() * max(0, 12 - (int) $this->free_months);
        }

        if ($this->discount_percentage > 0) {
            $price = $price * (1 - ($this->discount_percentage / 100));
        }

        return round($price, 2);
    }

    public function getPriceForPeriod(string $period): float
    {
        return strtolower($period) === 'yearly'
            ? $this->getYearlyPriceWithOffers()
            : $this->getMonthlyPrice();
    }

    public function getPriceBreakdown(string $period): array
    {
        $period = strtolower($period);
        $monthly = $this->getMonthlyPrice();
        $yearlyBase = $this->getBaseYearlyPrice();
        $yearlyWithOffers = $this->getYearlyPriceWithOffers();

        return [
            'period' => $period,
            'monthly_price' => $monthly,
            'yearly_base_price' => $yearlyBase,
            'price' => $period === 'yearly' ? $yearlyWithOffers : $monthly,
            'discount_percentage' => (float) $this->discount_percentage,
            'free_months' => (int) $this->free_months,
        ];
    }

    public function subscriptions()
    {
        return $this->hasMany(UserSubscription::class);
    }
}
