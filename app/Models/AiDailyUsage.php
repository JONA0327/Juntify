<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiDailyUsage extends Model
{
    use HasFactory;

    protected $table = 'ai_daily_usage';

    protected $fillable = [
        'user_id',
        'usage_date',
        'message_count',
        'document_count',
    ];

    protected $casts = [
        'usage_date' => 'date',
        'message_count' => 'integer',
        'document_count' => 'integer',
    ];
}
