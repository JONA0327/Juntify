<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\TranscriptionLaravel;

class Transcription extends Model
{
    use HasFactory;

    public $timestamps = false;

    /**
     * The table associated with the model.
     */
    protected $table = 'transcriptions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'meeting_id',
        'time',
        'speaker',
        'text',
        'display_speaker',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(TranscriptionLaravel::class, 'meeting_id');
    }

    public function scopeByTime($query)
    {
        return $query->orderBy('time');
    }
}

