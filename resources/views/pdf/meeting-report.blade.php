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
        <h1>Juntify</h1>
        @if(isset($organization) && $organization?->imagen)
            <img src="{{ $organization->imagen }}" alt="Logo" class="org-logo">
        @endif
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
                        <th>Tarea</th>
                        <th>Responsable</th>
                        <th>Fecha límite</th>
                        <th>Estado</th>
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
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center">No hay tareas</td>
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

