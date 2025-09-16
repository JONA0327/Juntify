<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\TranscriptionLaravel;

class KeyPoint extends Model
{
    use HasFactory;

    public $timestamps = false;

    /**
     * The table associated with the model.
     */
    protected $table = 'key_points';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'meeting_id',
        'point_text',
        'order_num',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(TranscriptionLaravel::class, 'meeting_id');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order_num');
    }
}

