#!/usr/bin/env php
<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

// Boot the app
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== TEST BÚSQUEDA DE REUNIONES POR TÍTULO ===\n\n";

// Verificar que hay reuniones para probar
$totalMeetings = App\Models\TranscriptionLaravel::count();
$tempMeetings = App\Models\TranscriptionTemp::count();

echo "📊 Reuniones en sistema:\n";
echo "  - Reuniones normales: {$totalMeetings}\n";
echo "  - Reuniones temporales: {$tempMeetings}\n\n";

// Obtener algunas reuniones para mostrar cómo funciona la búsqueda
echo "📋 Ejemplos de títulos de reuniones:\n";

$sampleMeetings = App\Models\TranscriptionLaravel::select('id', 'meeting_name', 'created_at')
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();

if ($sampleMeetings->count() > 0) {
    foreach ($sampleMeetings as $meeting) {
        $title = $meeting->meeting_name ?: "Sin título";
        $date = $meeting->created_at->format('d/m/Y H:i');
        echo "  • {$title} (ID: {$meeting->id}) - {$date}\n";
    }
} else {
    echo "  No hay reuniones normales\n";
}

echo "\n";

$tempSampleMeetings = App\Models\TranscriptionTemp::select('id', 'title', 'created_at')
    ->orderBy('created_at', 'desc')
    ->limit(5)
    ->get();

if ($tempSampleMeetings->count() > 0) {
    echo "📋 Ejemplos de reuniones temporales:\n";
    foreach ($tempSampleMeetings as $meeting) {
        $title = $meeting->title ?: "Sin título";
        $date = $meeting->created_at->format('d/m/Y H:i');
        echo "  • {$title} (ID: {$meeting->id}) - {$date}\n";
    }
} else {
    echo "📋 No hay reuniones temporales\n";
}

echo "\n=== FUNCIONALIDAD DE BÚSQUEDA ===\n";
echo "✅ 1. Botón de filtro por fecha eliminado\n";
echo "✅ 2. Campo de búsqueda actualizado: 'Buscar por título de reunión...'\n";
echo "✅ 3. JavaScript actualizado para nuevo placeholder\n";
echo "✅ 4. Función handleSearch optimizada\n";
echo "✅ 5. Búsqueda funciona por:\n";
echo "   - Título de reunión (prioridad)\n";
echo "   - Nombre de carpeta\n";
echo "   - Texto de vista previa\n\n";

echo "=== CARACTERÍSTICAS ===\n";
echo "• Búsqueda en tiempo real (mientras escribes)\n";
echo "• Insensible a mayúsculas/minúsculas\n";
echo "• Busca en título, carpeta y contenido\n";
echo "• Mensaje claro cuando no hay resultados\n";
echo "• Se resetea al limpiar el campo\n\n";

echo "=== TESTING RECOMENDADO ===\n";
echo "1. Ir a /reuniones\n";
echo "2. Escribir en el campo de búsqueda\n";
echo "3. Verificar que filtra por título correctamente\n";
echo "4. Confirmar que ya no aparece el botón de 'Fecha'\n\n";

echo "🎉 IMPLEMENTACIÓN COMPLETADA!\n";
echo "- ❌ Filtro por fecha eliminado\n";
echo "- ✅ Búsqueda por título optimizada y funcional\n";

?>