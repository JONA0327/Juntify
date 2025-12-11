<?php
/**
 * Script para dividir archivo SQL grande en archivos más pequeños
 * Ejecutar con: php scripts/split_sql.php
 */

$sourceFile = 'juntify_migration_2025-12-11_12-11-14.sql';
$maxSize = 1 * 1024 * 1024; // 1MB por archivo

if (!file_exists($sourceFile)) {
    echo "❌ Archivo $sourceFile no encontrado\n";
    echo "Ejecuta primero: php scripts/export_sql.php\n";
    exit(1);
}

echo "🔪 DIVIDIENDO ARCHIVO SQL\n";
echo "========================\n";
echo "📄 Archivo fuente: $sourceFile\n";
echo "📊 Tamaño máximo por archivo: " . number_format($maxSize / 1024, 0) . " KB\n\n";

$content = file_get_contents($sourceFile);
$lines = explode("\n", $content);

$currentFile = 1;
$currentSize = 0;
$currentContent = "";

// Cabecera común
$header = "-- =====================================================\n";
$header .= "-- MIGRACIÓN JUNTIFY - PARTE {PART}\n";
$header .= "-- =====================================================\n\n";
$header .= "SET FOREIGN_KEY_CHECKS = 0;\n";
$header .= "SET AUTOCOMMIT = 0;\n";
$header .= "START TRANSACTION;\n\n";

$footer = "\nCOMMIT;\n";
$footer .= "SET FOREIGN_KEY_CHECKS = 1;\n";
$footer .= "SET AUTOCOMMIT = 1;\n";

$inTable = false;
$tableBuffer = "";

foreach ($lines as $line) {
    $line = trim($line);

    if (empty($line) || strpos($line, '--') === 0) {
        // Líneas de comentario o vacías
        if (strpos($line, '-- Tabla:') === 0) {
            $inTable = true;
            $tableBuffer = $line . "\n";
        } elseif ($inTable) {
            $tableBuffer .= $line . "\n";
        } else {
            $currentContent .= $line . "\n";
        }
        continue;
    }

    if (strpos($line, 'SET FOREIGN_KEY_CHECKS') === 0 ||
        strpos($line, 'START TRANSACTION') === 0 ||
        strpos($line, 'COMMIT') === 0 ||
        strpos($line, 'SET AUTOCOMMIT') === 0) {
        // Ignorar comandos de control - los agregamos nosotros
        continue;
    }

    $lineSize = strlen($line . "\n");

    if ($currentSize + $lineSize > $maxSize && $currentSize > 0) {
        // Guardar archivo actual
        $filename = "juntify_migration_part_$currentFile.sql";
        $fileContent = str_replace('{PART}', $currentFile, $header) . $currentContent . $footer;
        file_put_contents($filename, $fileContent);

        echo "✅ Creado: $filename (" . number_format(strlen($fileContent) / 1024, 2) . " KB)\n";

        // Iniciar nuevo archivo
        $currentFile++;
        $currentContent = $tableBuffer;
        $currentSize = strlen($tableBuffer);
        $tableBuffer = "";
        $inTable = false;
    }

    $currentContent .= $line . "\n";
    $currentSize += $lineSize;

    if (strpos($line, ');') !== false) {
        $inTable = false;
        $tableBuffer = "";
    }
}

// Guardar último archivo
if ($currentSize > 0) {
    $filename = "juntify_migration_part_$currentFile.sql";
    $fileContent = str_replace('{PART}', $currentFile, $header) . $currentContent . $footer;
    file_put_contents($filename, $fileContent);

    echo "✅ Creado: $filename (" . number_format(strlen($fileContent) / 1024, 2) . " KB)\n";
}

echo "\n🎉 DIVISIÓN COMPLETADA\n";
echo "=====================\n";
echo "📁 Total de archivos: $currentFile\n\n";

echo "📋 INSTRUCCIONES:\n";
echo "=================\n";
echo "1. Sube TODOS los archivos juntify_migration_part_X.sql a tu servidor\n";
echo "2. Ejecuta en orden:\n";
for ($i = 1; $i <= $currentFile; $i++) {
    echo "   SOURCE juntify_migration_part_$i.sql;\n";
}
echo "3. Verifica la importación\n\n";
?>
