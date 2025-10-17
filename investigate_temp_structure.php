<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== INVESTIGANDO ESTRUCTURA DE transcription_temps ===\n\n";

try {
    // 1. Verificar estructura de la tabla
    echo "1. ESTRUCTURA DE LA TABLA:\n";
    echo "===========================\n";

    $columns = \DB::select("DESCRIBE transcription_temps");

    echo "✅ Columnas en transcription_temps:\n";
    foreach ($columns as $column) {
        echo "   - {$column->Field} ({$column->Type})\n";
    }
    echo "\n";

    // 2. Verificar datos de la reunión ID 14
    echo "2. DATOS DE REUNIÓN ID 14:\n";
    echo "===========================\n";

    $meeting = \DB::select("SELECT * FROM transcription_temps WHERE id = 14");

    if (!empty($meeting)) {
        $meeting = $meeting[0];
        echo "✅ Reunión encontrada:\n";

        foreach ((array)$meeting as $field => $value) {
            echo "   - {$field}: {$value}\n";
        }
        echo "\n";
    }

    // 3. Verificar el modelo TranscriptionTemp
    echo "3. VERIFICANDO MODELO TranscriptionTemp:\n";
    echo "=========================================\n";

    $model = new \App\Models\TranscriptionTemp();
    $fillable = $model->getFillable();

    echo "✅ Campos fillable:\n";
    foreach ($fillable as $field) {
        echo "   - {$field}\n";
    }
    echo "\n";

    // 4. Buscar cómo se almacena la ruta del archivo
    echo "4. INVESTIGANDO CÓMO SE ALMACENA LA RUTA:\n";
    echo "==========================================\n";

    // Revisar el TranscriptionTempController para ver cómo se guarda
    $controllerPath = app_path('Http/Controllers/TranscriptionTempController.php');

    if (file_exists($controllerPath)) {
        echo "✅ Archivo del controlador existe\n";

        // Buscar método store o save
        $content = file_get_contents($controllerPath);

        if (preg_match('/function store.*?\{.*?\}/s', $content, $matches)) {
            echo "   📄 Método store encontrado (extracto):\n";
            $excerpt = substr($matches[0], 0, 500);
            echo "   " . str_replace("\n", "\n   ", $excerpt) . "...\n\n";
        }
    }

    // 5. Revisar cómo el asistente AI debería acceder a los datos
    echo "5. ESTRATEGIA PARA EL ASISTENTE AI:\n";
    echo "====================================\n";

    echo "💡 Posibles soluciones:\n";
    echo "1. El archivo .ju se encuentra usando el pattern:\n";
    echo "   temp_transcriptions/{user_id}/{title_slug}_{timestamp}.ju\n\n";

    echo "2. Podemos crear un método que busque el archivo basándose en:\n";
    echo "   - user_id del modelo\n";
    echo "   - title del modelo\n";
    echo "   - created_at para aproximar el timestamp\n\n";

    echo "3. O agregar un campo file_path a la tabla (migración)\n\n";

    // 6. Probar búsqueda de archivo para la reunión ID 14
    echo "6. PROBANDO BÚSQUEDA DE ARCHIVO PARA ID 14:\n";
    echo "============================================\n";

    $meeting = \App\Models\TranscriptionTemp::find(14);
    if ($meeting) {
        echo "✅ Reunión ID 14:\n";
        echo "   - User ID: {$meeting->user_id}\n";
        echo "   - Title: '{$meeting->title}'\n";
        echo "   - Created: {$meeting->created_at}\n";

        // Buscar archivo correspondiente
        $userDir = storage_path("app/temp_transcriptions/{$meeting->user_id}");
        $titleSlug = \Illuminate\Support\Str::slug($meeting->title);

        echo "   - Title slug: '{$titleSlug}'\n";
        echo "   - Buscando en: {$userDir}\n";

        if (is_dir($userDir)) {
            $files = glob($userDir . "/*.ju");
            echo "   - Archivos .ju encontrados: " . count($files) . "\n";

            foreach ($files as $file) {
                $filename = basename($file, '.ju');
                echo "     📄 {$filename}\n";

                if (str_contains($filename, 'kualifin')) {
                    echo "       ✅ COINCIDENCIA POSIBLE\n";

                    // Verificar contenido
                    $content = file_get_contents($file);
                    $data = json_decode($content, true);

                    if ($data && isset($data['transcription'])) {
                        echo "       ✅ Archivo válido con transcripción\n";
                        echo "       - Tareas: " . (isset($data['tasks']) ? count($data['tasks']) : 0) . "\n";

                        $relativePath = "temp_transcriptions/{$meeting->user_id}/" . basename($file);
                        echo "       - Ruta relativa: {$relativePath}\n";
                    }
                }
            }
        }
    }

    echo "\n=== INVESTIGACIÓN COMPLETA ===\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
