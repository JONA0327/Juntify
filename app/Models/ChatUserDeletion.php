<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatUserDeletion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'chat_id',
        'user_id',
        'deleted_at',
    ];

    protected $dates = ['deleted_at'];
}
