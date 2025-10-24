<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Te han asignado una nueva tarea</title>
    <style>
        .btn {
            display: inline-block;
            padding: 12px 24px;
            margin: 8px 4px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            text-align: center;
        }
        .btn-accept {
            background-color: #10b981;
            color: white;
        }
        .btn-reject {
            background-color: #ef4444;
            color: white;
        }
        .task-info {
            background-color: #f8fafc;
            border-left: 4px solid #3b82f6;
            padding: 16px;
            margin: 16px 0;
        }
    </style>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h2 style="color: #1d4ed8;">📋 Te han asignado una nueva tarea</h2>

        <p>Hola <strong>{{ $assignee->full_name ?: $assignee->username }}</strong>,</p>

        <p><strong>{{ $owner->full_name ?: $owner->username }}</strong> te ha asignado una nueva tarea en Juntify.</p>

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

        @if($message)
            <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 12px; margin: 16px 0;">
                <p style="margin: 0;"><strong>💬 Mensaje del asignador:</strong><br>{{ $message }}</p>
            </div>
        @endif

        <p><strong>¿Aceptas esta tarea?</strong></p>
        <p>Por favor, responde si puedes encargarte de esta tarea:</p>

        <div style="text-align: center; margin: 24px 0;">
            <a href="{{ $acceptUrl }}" class="btn btn-accept">✅ Aceptar Tarea</a>
            <a href="{{ $rejectUrl }}" class="btn btn-reject">❌ Rechazar Tarea</a>
        </div>

        <div style="background-color: #f1f5f9; padding: 12px; border-radius: 6px; margin: 20px 0; font-size: 14px; color: #64748b;">
            <p style="margin: 0;"><strong>💡 Tip:</strong> Si aceptas la tarea, podrás ver más detalles, agregar comentarios y actualizar el progreso desde tu panel de tareas en Juntify.</p>
        </div>

        <hr style="margin: 24px 0; border: none; border-top: 1px solid #e2e8f0;">

        <p style="margin-top: 24px; font-size: 14px; color: #64748b;">
            Este email fue enviado porque te han asignado una tarea en Juntify.<br>
            Si tienes problemas con los botones, puedes copiar y pegar estos enlaces en tu navegador:<br>
            <strong>Aceptar:</strong> {{ $acceptUrl }}<br>
            <strong>Rechazar:</strong> {{ $rejectUrl }}
        </p>

        <p style="margin-top: 24px;">
            Saludos,<br>
            <strong>Equipo Juntify</strong>
        </p>
    </div>
</body>
</html>
