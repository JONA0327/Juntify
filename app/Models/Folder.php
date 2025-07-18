<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Folder extends Model
{
    protected $fillable = [
        'google_token_id',
        'google_id',
        'name',
        'parent_id',
    ];
}
