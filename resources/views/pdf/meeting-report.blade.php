<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte de Reunión</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; }
        h1 { font-size: 20px; }
        h2 { font-size: 16px; margin-top: 20px; }
        pre { white-space: pre-wrap; }
    </style>
</head>
<body>
    <h1>{{ $meeting->meeting_name }}</h1>

    @if(!empty($summary))
        <h2>Resumen</h2>
        <p>{{ $summary }}</p>
    @endif

    @if(!empty($keyPoints))
        <h2>Puntos Clave</h2>
        <ul>
            @foreach($keyPoints as $point)
                <li>{{ $point }}</li>
            @endforeach
        </ul>
    @endif

    @if(!empty($transcription))
        <h2>Transcripción</h2>
        <pre>{{ $transcription }}</pre>
    @endif

    @if($tasks->isNotEmpty())
        <h2>Tareas</h2>
        <ul>
            @foreach($tasks as $task)
                <li>{{ $task->text }} @if($task->assignee) - {{ $task->assignee }} @endif</li>
            @endforeach
        </ul>
    @endif
</body>
</html>

