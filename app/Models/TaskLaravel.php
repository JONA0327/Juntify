<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskLaravel extends Model
{
    use HasFactory;

    protected $table = 'tasks_laravel';

    protected $fillable = [
        'username',
        'meeting_id',
        'tarea',
        'prioridad',
        'fecha_inicio',
        'fecha_limite',
    'hora_limite',
        'descripcion',
    'asignado',
        'progreso',
        'google_event_id',
        'google_calendar_id',
        'calendar_synced_at',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_limite' => 'date',
    'hora_limite' => 'string',
    'asignado' => 'string',
        'progreso' => 'integer',
        'calendar_synced_at' => 'datetime',
    ];

    public function meeting()
    {
        return $this->belongsTo(TranscriptionLaravel::class, 'meeting_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'username', 'username');
    }
}
