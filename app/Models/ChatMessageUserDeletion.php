<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatMessageUserDeletion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'chat_message_id',
        'deleted_at',
    ];

    protected $dates = ['deleted_at'];
}
