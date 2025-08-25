<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de Reunión</title>
    @php($cssPath = public_path('css/reports.css'))
    @if(file_exists($cssPath))
        <style>
            {!! file_get_contents($cssPath) !!}
        </style>
    @endif
</head>
<body class="report-body">
    <header class="report-header">
        <div class="report-topbar">
            @if(isset($organization) && $organization?->imagen)
                <img src="{{ $organization->imagen }}" alt="Logo" class="org-logo">
            @else
                <h1 class="report-brand">Juntify</h1>
            @endif
            <div class="report-summary">
                <h1 class="report-title">{{ $reportTitle ?? 'Reporte de Reunión' }}</h1>
                <p class="report-date">{{ $reportDate ?? now()->format('d/m/Y') }}</p>
            </div>
        </div>
        <div class="meeting-details">
            @isset($meetingName)
                <p class="meeting-name">{{ $meetingName }}</p>
            @endisset
            @isset($participants)
                <p class="participants">Participantes: {{ is_array($participants) ? implode(', ', $participants) : $participants }}</p>
            @endisset
        </div>
    </header>

    @isset($summary)
        <section class="report-section summary-section">
            <h2>Resumen</h2>
            <p>{!! nl2br(e($summary)) !!}</p>
        </section>
    @endisset

    @isset($transcription)
        @php
            $segments = [];
            foreach(preg_split("/\r\n|\n|\r/", $transcription) as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                if (strpos($line, ':') !== false) {
                    [$speaker, $text] = explode(':', $line, 2);
                    $segments[] = ['speaker' => trim($speaker), 'text' => trim($text)];
                } else {
                    $segments[] = ['speaker' => '', 'text' => $line];
                }
            }
        @endphp
        <section class="report-section transcription-section">
            <h2>Transcripción</h2>
            <div class="transcription-table">
                @foreach($segments as $segment)
                    <div class="transcription-segment">
                        @if($segment['speaker'] !== '')
                            <div class="transcription-speaker">{{ e($segment['speaker']) }}</div>
                        @endif
                        <div class="transcription-text">{{ e($segment['text']) }}</div>
                    </div>
                @endforeach
            </div>
        </section>
    @endisset

    @if(!empty($keyPoints))
        <section class="report-section key-points-section">
            <h2>Puntos clave / Acuerdos</h2>
            <ul>
                @foreach($keyPoints as $point)
                    <li>{{ $point }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    @isset($additionalNotes)
        <section class="report-section observations-section">
            <h2>Observaciones adicionales</h2>
            <p>{!! nl2br(e($additionalNotes)) !!}</p>
        </section>
    @endisset

    @isset($tasks)
        <section class="report-section">
            <h2>Tareas</h2>
            <table class="tasks-table">
                <thead>
                    <tr>
                        <th>Tarea</th>
                        <th>Responsable</th>
                        <th>Fecha límite</th>
                        <th>Estado</th>
                        <th>Prioridad</th>
                        <th>Comentarios</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tasks as $task)
                        <tr>
                            <td class="{{ empty($task['text']) ? 'text-center' : '' }}">{{ $task['text'] ?? '-' }}</td>
                            <td class="text-center">{{ $task['assignee'] ?? '-' }}</td>
                            <td class="text-center">
                                @if(!empty($task['due_date']))
                                    {{ \Carbon\Carbon::parse($task['due_date'])->format('d/m/Y') }}
                                @else
                                    -
                                @endif
                            </td>
                            <td class="text-center">
                                @if(isset($task['completed']))
                                    {{ $task['completed'] ? 'Completada' : 'Pendiente' }}
                                @else
                                    -
                                @endif
                            </td>
                            <td class="text-center">{{ $task['priority'] ?? '-' }}</td>
                            <td>{{ $task['description'] ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center">No hay tareas</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    @endisset

    <footer class="report-footer">
        Generado el {{ now()->format('d/m/Y H:i') }}
    </footer>
</body>
</html>

