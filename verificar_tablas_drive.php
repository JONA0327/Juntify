<?php
// Script de verificación de tablas Drive
// Ejecutar con: php verificar_tablas_drive.php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Folder;
use App\Models\Subfolder;
use App\Models\OrganizationFolder;
use App\Models\OrganizationSubfolder;
use Illuminate\Support\Facades\Schema;

echo "🔍 Verificando tablas de Drive...\n\n";

// Verificar tablas personales
echo "📁 TABLAS PERSONALES:\n";
echo "- folder table: " . (Schema::hasTable('folder') ? '✅ Existe' : '❌ No existe') . "\n";
echo "- subfolder table: " . (Schema::hasTable('subfolder') ? '✅ Existe' : '❌ No existe') . "\n";

// Verificar tablas organizacionales
echo "\n🏢 TABLAS ORGANIZACIONALES:\n";
echo "- organization_folder table: " . (Schema::hasTable('organization_folder') ? '✅ Existe' : '❌ No existe') . "\n";
echo "- organization_subfolders table: " . (Schema::hasTable('organization_subfolders') ? '✅ Existe' : '❌ No existe') . "\n";

// Verificar modelos
echo "\n🔧 CONFIGURACIÓN DE MODELOS:\n";

try {
    $folder = new Folder();
    echo "- Folder model table: " . $folder->getTable() . " ✅\n";
} catch (Exception $e) {
    echo "- Folder model: ❌ Error: " . $e->getMessage() . "\n";
}

try {
    $subfolder = new Subfolder();
    echo "- Subfolder model table: " . $subfolder->getTable() . " ✅\n";
} catch (Exception $e) {
    echo "- Subfolder model: ❌ Error: " . $e->getMessage() . "\n";
}

try {
    $orgFolder = new OrganizationFolder();
    echo "- OrganizationFolder model table: " . $orgFolder->getTable() . " ✅\n";
} catch (Exception $e) {
    echo "- OrganizationFolder model: ❌ Error: " . $e->getMessage() . "\n";
}

try {
    $orgSubfolder = new OrganizationSubfolder();
    echo "- OrganizationSubfolder model table: " . $orgSubfolder->getTable() . " ✅\n";
} catch (Exception $e) {
    echo "- OrganizationSubfolder model: ❌ Error: " . $e->getMessage() . "\n";
}

echo "\n✅ Verificación completada!\n";
echo "\nRESUMEN:\n";
echo "Personal: folder + subfolder\n";
echo "Organization: organization_folder + organization_subfolders\n";
