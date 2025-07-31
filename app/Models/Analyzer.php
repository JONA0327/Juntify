<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Analyzer extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'description',
        'icon',
        'system_prompt',
        'user_prompt_template',
        'temperature',
        'is_system',
        'created_at',
        'updated_at',
        'created_by',
        'updated_by',
    ];
}
