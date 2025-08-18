<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeetingShare extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'meeting_id',
        'from_username',
        'to_username',
    ];
}

