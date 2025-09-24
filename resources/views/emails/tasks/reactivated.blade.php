<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Tarea reactivada</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937;">
    <h2 style="color:#1d4ed8;">La tarea "{{ $task->tarea }}" se ha reactivado</h2>
    <p>Hola {{ $task->asignado ?? 'equipo' }},</p>
    <p>El administrador <strong>{{ $owner->full_name ?? $owner->username }}</strong> reactivó la tarea vinculada a la reunión <strong>{{ optional($task->meeting)->meeting_name ?? 'Sin nombre' }}</strong>.</p>
    @if(!empty($reason))
        <p><strong>Motivo:</strong> {{ $reason }}</p>
    @endif
    <p>Puedes ingresar a Juntify para revisar los comentarios y los archivos adjuntos asociados.</p>
    <p style="margin-top: 24px;">Gracias,<br>Equipo Juntify</p>
</body>
</html>
