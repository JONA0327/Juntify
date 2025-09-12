<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Container extends Model
{
    protected $table = 'meeting_content_containers';

    protected $fillable = [
        'username',
        'name',
        'description',
        'is_active',
    ];

    public function meetings(): BelongsToMany
    {
        return $this->belongsToMany(TranscriptionLaravel::class, 'container_meetings', 'container_id', 'meeting_id');
    }
}

