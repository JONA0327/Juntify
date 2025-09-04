<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Meeting extends Model
{
    use HasFactory;

    protected $table = 'meetings';

    protected $fillable = [
        'title',
        'date',
        'duration',
        'participants',
        'summary',
        'recordings_folder_id',
        'username',
        'speaker_map',
    ];

    protected $casts = [
        'date' => 'datetime',
        'speaker_map' => 'array',
    ];

    public function keyPoints()
    {
        return $this->hasMany(KeyPoint::class, 'meeting_id');
    }

    public function transcriptions()
    {
        return $this->hasMany(Transcription::class, 'meeting_id');
    }
}
