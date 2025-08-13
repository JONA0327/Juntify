<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleToken extends Model
{
    public $timestamps = true;
    protected $fillable = [
        'username',
        'access_token',
        'refresh_token',
        'expiry_date',
        'recordings_folder_id',
    ];

    protected $casts = [
        'expiry_date' => 'datetime',
    ];
}
