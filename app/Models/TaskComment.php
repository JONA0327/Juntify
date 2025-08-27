<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskComment extends Model
{
    public $timestamps = false; // tabla ya tiene columna 'date'

    protected $table = 'task_comments';

    protected $fillable = [
        'task_id',
        'author',
        'text',
        'date',
    ];

    protected $casts = [
        'date' => 'datetime',
    ];

    public function task()
    {
        return $this->belongsTo(TaskLaravel::class, 'task_id');
    }
}

