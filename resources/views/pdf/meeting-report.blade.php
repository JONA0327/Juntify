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
        .org-logo { max-height: 80px; }
        .text-center { text-align: center; }
    </style>
</head>
<body>
    <div class="text-center">
        @if($organization && $organization->imagen)
            <img class="org-logo" src="{{ $organization->imagen }}" alt="{{ $organization->nombre_organizacion }}">
        @endif
        <h1>{{ $meeting->meeting_name }}</h1>
        @if($organization)
            <p>{{ $organization->nombre_organizacion }}</p>
        @endif
    </div>

    @foreach($sections as $section)
        <h2>{{ $section['title'] }}</h2>
        @if(is_array($section['content']))
            <ul>
                @foreach($section['content'] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        @else
            @if($section['title'] === 'Transcripción')
                <pre>{{ $section['content'] }}</pre>
            @else
                <p>{{ $section['content'] }}</p>
            @endif
        @endif
    @endforeach
</body>
</html>

