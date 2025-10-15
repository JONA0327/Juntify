<!DOCTYPE html>
<html>
<head>
    <title>Debug Plans</title>
</head>
<body>
    <h1>Planes actuales en la base de datos:</h1>

    @foreach($plans as $plan)
    <div style="border: 1px solid #ccc; margin: 10px; padding: 15px; border-radius: 5px;">
        <h3>{{ $plan->name }} ({{ $plan->code }})</h3>
        <p><strong>Precio:</strong> ${{ $plan->price }} {{ $plan->currency }}</p>
        <p><strong>Descripción:</strong> {{ $plan->description }}</p>
        <p><strong>Features:</strong></p>
        <ul>
            @foreach($plan->features as $feature)
                <li>{{ $feature }}</li>
            @endforeach
        </ul>
    </div>
    @endforeach
</body>
</html>
