<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArchivoReunion extends Model
{
    protected $table = 'archivos_reuniones';

    protected $fillable = [
        'task_id',
        'username',
        'name',
        'mime_type',
        'size',
        'drive_file_id',
        'drive_folder_id',
        'drive_web_link',
        'drive_type',
        'organization_id',
    ];

    protected $casts = [
        'size' => 'integer',
        'organization_id' => 'integer',
    ];

    public function task()
    {
        return $this->belongsTo(TaskLaravel::class, 'task_id');
    }
}

