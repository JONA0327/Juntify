<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon; // for type hints

class UserSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id','plan_id','status','starts_at','ends_at','cancelled_at','external_reference','meta'
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'meta' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function scopeActive($q)
    {
        return $q->where('status','active')->where(function($qq){
            $qq->whereNull('ends_at')->orWhere('ends_at','>', now());
        });
    }
}
