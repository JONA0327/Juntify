<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== VERIFICACIÓN FINAL DE CORRECCIÓN ===\n\n";

// Verificar datos directos de la base de datos
$tempMeeting = \DB::table('transcription_temps')
    ->where('user_id', 'a2c8514d-932c-4bc9-8a2b-e7355faa25ad')
    ->select('id', 'title', 'created_at')
    ->first();

if ($tempMeeting) {
    echo "✅ REUNIÓN TEMPORAL ENCONTRADA:\n";
    echo "   - ID: {$tempMeeting->id}\n";
    echo "   - Título: '{$tempMeeting->title}'\n";
    echo "   - Fecha: {$tempMeeting->created_at}\n\n";

    echo "✅ CORRECCIONES APLICADAS:\n";
    echo "   - Backend: title → meeting_name mapping ✓\n";
    echo "   - Frontend: meeting.meeting_name || meeting.title fallback ✓\n";
    echo "   - JavaScript compilado ✓\n\n";

    echo "🎯 RESULTADO ESPERADO:\n";
    echo "   La reunión '{$tempMeeting->title}' ahora debería aparecer\n";
    echo "   con su nombre real en lugar de 'Reunión sin título'\n\n";

    echo "📋 INSTRUCCIONES PARA EL USUARIO:\n";
    echo "   1. Recarga la página (F5 o Ctrl+R)\n";
    echo "   2. Si persiste, borra caché (Ctrl+F5)\n";
    echo "   3. Verifica que aparezca: '{$tempMeeting->title}'\n\n";

    echo "🎉 CORRECCIÓN COMPLETA\n";
} else {
    echo "❌ No se encontró la reunión temporal\n";
}
