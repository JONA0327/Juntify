<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Container extends Model
{
    protected $fillable = [
        'username',
        'name',
    ];

    public function meetings(): BelongsToMany
    {
        return $this->belongsToMany(TranscriptionLaravel::class, 'container_meetings', 'container_id', 'meeting_id');
    }
}

