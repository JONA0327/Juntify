<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupCode extends Model
{
    protected $fillable = [
        'group_id',
        'code',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}

