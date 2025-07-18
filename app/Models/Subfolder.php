<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subfolder extends Model
{
    protected $fillable = [
        'folder_id',
        'google_id',
        'name',
    ];
}
