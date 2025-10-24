<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Tu tarea ha sido rechazada</title>
    <style>
        .task-info {
            background-color: #fef2f2;
            border-left: 4px solid #ef4444;
            padding: 16px;
            margin: 16px 0;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            margin: 8px 4px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            text-align: center;
            background-color: #3b82f6;
            color: white;
        }
    </style>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h2 style="color: #ef4444;">❌ Tu tarea ha sido rechazada</h2>

        <p>Hola <strong>{{ $owner->full_name ?: $owner->username }}</strong>,</p>

        <p><strong>{{ $rejector->full_name ?: $rejector->username }}</strong> ha rechazado la tarea que le asignaste.</p>

        <div class="task-info">
            <h3 style="margin-top: 0; color: #374151;">📝 {{ $task->tarea }}</h3>

            @if($task->descripcion)
                <p><strong>Descripción:</strong><br>{{ $task->descripcion }}</p>
            @endif

            @if($task->fecha_limite)
                <p><strong>📅 Fecha límite:</strong> {{ $task->fecha_limite->format('d/m/Y') }}</p>
            @endif

            @if($task->hora_limite)
                <p><strong>⏰ Hora límite:</strong> {{ $task->hora_limite }}</p>
            @endif

            <p><strong>📊 Prioridad:</strong>
                @if($task->prioridad === 'alta')
                    🔴 Alta
                @elseif($task->prioridad === 'media')
                    🟡 Media
                @else
                    🟢 Baja
                @endif
            </p>

            @if($task->meeting)
                <p><strong>🎯 Reunión:</strong> {{ $task->meeting->meeting_name ?: 'Sin nombre' }}</p>
            @endif
        </div>

        <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 12px; margin: 16px 0;">
            <p style="margin: 0;"><strong>👤 Rechazado por:</strong> {{ $rejector->full_name ?: $rejector->username }}</p>
            <p style="margin: 8px 0 0 0;"><strong>📧 Email:</strong> {{ $rejector->email }}</p>
        </div>

        @if($reason)
            <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 12px; margin: 16px 0;">
                <p style="margin: 0;"><strong>💬 Motivo del rechazo:</strong><br>{{ $reason }}</p>
            </div>
        @endif

        <div style="background-color: #f1f5f9; padding: 12px; border-radius: 6px; margin: 20px 0; font-size: 14px; color: #64748b;">
            <p style="margin: 0;"><strong>💡 ¿Qué puedes hacer ahora?</strong></p>
            <ul style="margin: 8px 0 0 0; padding-left: 20px;">
                <li>Asignar la tarea a otra persona</li>
                <li>Modificar los detalles de la tarea</li>
                <li>Contactar directamente con {{ $rejector->full_name ?: $rejector->username }} para más información</li>
                <li>Realizar la tarea tú mismo</li>
            </ul>
        </div>

        <div style="text-align: center; margin: 24px 0;">
            <a href="{{ $taskUrl }}" class="btn">📋 Ver Tarea en Juntify</a>
        </div>

        <hr style="margin: 24px 0; border: none; border-top: 1px solid #e2e8f0;">

        <p style="margin-top: 24px; font-size: 14px; color: #64748b;">
            Este email fue enviado porque una tarea que asignaste fue rechazada.<br>
            Puedes gestionar todas tus tareas desde tu panel en Juntify: {{ $taskUrl }}
        </p>

        <p style="margin-top: 24px;">
            Saludos,<br>
            <strong>Equipo Juntify</strong>
        </p>
    </div>
</body>
</html>
