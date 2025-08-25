<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de Reunión</title>
    @vite('resources/css/reports.css')
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

    @isset($sections)
        @foreach($sections as $section)
            @if(!empty($section['content']))
                <section class="report-section">
                    @isset($section['title'])
                        <h2>{{ $section['title'] }}</h2>
                    @endisset
                    {!! $section['content'] !!}
                </section>
            @endif
        @endforeach
    @endisset

    @isset($tasks)
        <section class="report-section">
            <h2>Tareas</h2>
            <table class="tasks-table">
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Descripción</th>
                        <th>Observaciones</th>
                        <th>Fecha</th>
                        <th>Progreso</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tasks as $task)
                        <tr>
                            <td class="text-center">{{ $loop->iteration }}</td>
                            <td class="{{ empty($task['text']) ? 'text-center' : '' }}">{{ $task['text'] ?? '-' }}</td>
                            <td>{{ $task['description'] ?? '-' }}</td>
                            <td class="text-center">
                                @if(!empty($task['due_date']))
                                    {{ \Carbon\Carbon::parse($task['due_date'])->format('d/m/Y') }}
                                @else
                                    -
                                @endif
                            </td>
                            <td class="text-center">
                                @if(isset($task['completed']) && $task['completed'])
                                    100%
                                @else
                                    {{ isset($task['progress']) ? $task['progress'] : 0 }}%
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center">No hay tareas</td>
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
