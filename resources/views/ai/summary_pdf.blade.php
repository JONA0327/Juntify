<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #111; font-size: 12px; }
        h1 { font-size: 20px; margin-bottom: 6px; }
        h2 { font-size: 16px; margin: 18px 0 8px; }
        .meta { color: #666; font-size: 11px; margin-bottom: 16px; }
        .fragment { margin-bottom: 10px; }
        .cite { color: #555; font-size: 11px; }
        .footer { margin-top: 24px; border-top: 1px solid #ddd; padding-top: 8px; font-size: 10px; color: #666; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="meta">
        Generado para {{ $user->full_name ?? $user->username }} el {{ $generatedAt->format('d/m/Y H:i') }}
        @if($session->context_type === 'meeting')
            — Reunión ID: {{ $session->context_id }}
        @elseif($session->context_type === 'container')
            — Contenedor ID: {{ $session->context_id }}
        @endif
    </div>

    <h2>Contenido</h2>
    @php $i = 1; @endphp
    @foreach($fragments as $frag)
        <div class="fragment">
            <strong>{{ $i++ }}.</strong>
            <div>{{ $frag['text'] ?? '' }}</div>
            @if(!empty($frag['citation']))
                <div class="cite">Fuente: {{ $frag['citation'] }}</div>
            @endif
        </div>
    @endforeach

    @if(!empty($citations))
        <h2>Fuentes citadas</h2>
        <ol>
            @foreach($citations as $c)
                <li>{{ $c }}</li>
            @endforeach
        </ol>
    @endif

    <div class="footer">
        Documento generado automáticamente por Juntify AI a partir de datos disponibles (resúmenes, puntos clave y segmentos de transcripción). Este documento no sustituye a la revisión humana.
    </div>
</body>
</html>
