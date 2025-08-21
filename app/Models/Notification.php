<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'remitente',
        'emisor',
        'status',
        'message',
        'type',
    ];

    public $timestamps = true;
}
