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
            <h1 class="report-brand">JUNTIFY</h1>
            <div class="report-generated">Generado el: {{ ($reportGeneratedAt ?? now())->locale('es')->translatedFormat('d MMM yyyy') }}</div>
        </div>
        <div class="meeting-details">
            <div class="meeting-meta">
                @isset($meetingName)
                    <p class="meeting-name">{{ $meetingName }}</p>
                @endisset
                @isset($reportTitle)
                    <p class="meeting-subtitle">{{ $reportTitle }}</p>
                @endisset
                @isset($participants)
                    <p class="participants">Participantes: {{ is_array($participants) ? implode(', ', $participants) : $participants }}</p>
                @endisset
            </div>
        </div>
    </header>

    <main class="report-content">
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
    </main>

    <footer class="report-footer">
        <span>Generado el {{ ($reportGeneratedAt ?? now())->format('d/m/Y H:i') }}</span>
        <span> | Página <script type="text/php"> echo $PAGE_NUM; </script> de <script type="text/php"> echo $PAGE_COUNT; </script></span>
        <script type="text/php">
            if (isset($pdf)) {
                $generatedAt = "{{ ($reportGeneratedAt ?? now())->format('d/m/Y H:i') }}";
                $text = "Generado el " . $generatedAt . " | Página {PAGE_NUM} de {PAGE_COUNT}";
                $font = $fontMetrics->get_font("helvetica", "normal");
                $size = 9;
                $w = $pdf->get_width();
                $h = $pdf->get_height();
                $y = $h - 28; // slightly above edge
                $pdf->page_text(($w - $fontMetrics->get_text_width($text, $font, $size)) / 2, $y, $text, $font, $size, [1,1,1]);
            }
        </script>
    </footer>
</body>
</html>

