<?php
/**
 * Script para limpiar archivos temporales expirados
 * Ejecutar con: php cleanup_temp_files.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\TranscriptionTemp;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

// Inicializar Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🧹 Iniciando limpieza de archivos temporales...\n\n";

// Buscar registros expirados
$expiredRecords = TranscriptionTemp::where('expires_at', '<', Carbon::now())->get();

echo "📊 Registros expirados encontrados: " . $expiredRecords->count() . "\n\n";

$deletedFiles = 0;
$deletedRecords = 0;
$totalSize = 0;

foreach ($expiredRecords as $record) {
    echo "🗂️  Procesando: {$record->title}\n";
    echo "   Expiró: {$record->expires_at}\n";

    // Eliminar archivo de audio
    if ($record->audio_path && Storage::disk('local')->exists($record->audio_path)) {
        $size = Storage::disk('local')->size($record->audio_path);
        $totalSize += $size;
        Storage::disk('local')->delete($record->audio_path);
        echo "   ✅ Audio eliminado (" . number_format($size / 1024 / 1024, 2) . " MB)\n";
        $deletedFiles++;
    }

    // Eliminar archivo de transcripción
    if ($record->transcription_path && Storage::disk('local')->exists($record->transcription_path)) {
        Storage::disk('local')->delete($record->transcription_path);
        echo "   ✅ Transcripción eliminada\n";
        $deletedFiles++;
    }

    // Eliminar registro de la base de datos
    $record->delete();
    $deletedRecords++;
    echo "   ✅ Registro eliminado\n\n";
}

echo "🏁 Limpieza completada:\n";
echo "   📁 Archivos eliminados: {$deletedFiles}\n";
echo "   🗃️  Registros eliminados: {$deletedRecords}\n";
echo "   💾 Espacio liberado: " . number_format($totalSize / 1024 / 1024, 2) . " MB\n";

if ($deletedRecords === 0) {
    echo "   ✨ No hay archivos temporales expirados para limpiar.\n";
}
?>
