<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlanLimit extends Model
{
    use HasFactory;

    protected $table = 'plan_limits';

    protected $fillable = [
        'role',
        'max_meetings_per_month',
        'max_duration_minutes',
        'allow_postpone',
        'warn_before_minutes',
    ];
}
