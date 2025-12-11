<?php
// Script de prueba para verificar que se agrega automáticamente el dueño como integrante
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../bootstrap/app.php';

use App\Models\Empresa;
use App\Models\IntegrantesEmpresa;
use App\Models\User;

echo "🧪 PRUEBA DE CREACIÓN AUTOMÁTICA DE EMPRESA CON INTEGRANTE\n";
echo "=========================================================\n\n";

try {
    // Buscar un usuario existente para la prueba
    $user = User::where('roles', 'enterprise')->orWhere('roles', 'founder')->first();

    if (!$user) {
        echo "❌ No se encontró ningún usuario con rol 'enterprise' o 'founder'\n";
        echo "Creando usuario de prueba...\n";

        $user = User::create([
            'name' => 'Usuario Prueba',
            'email' => 'prueba_empresa_' . time() . '@test.com',
            'password' => bcrypt('password123'),
            'roles' => 'enterprise'
        ]);

        echo "✅ Usuario de prueba creado: {$user->id}\n";
    }

    echo "👤 Usuario seleccionado:\n";
    echo "ID: {$user->id}\n";
    echo "Nombre: {$user->name}\n";
    echo "Email: {$user->email}\n";
    echo "Rol: {$user->roles}\n\n";

    // Verificar que el usuario no tenga ya una empresa
    $empresaExistente = Empresa::where('iduser', $user->id)->first();
    if ($empresaExistente) {
        echo "⚠️  El usuario ya tiene una empresa: {$empresaExistente->nombre_empresa}\n";
        echo "Usando empresa existente para verificar integrantes...\n\n";
        $empresa = $empresaExistente;
    } else {
        echo "🔄 Creando nueva empresa...\n";

        // Crear empresa usando el mismo proceso que el controlador
        $empresa = Empresa::create([
            'iduser' => $user->id,
            'nombre_empresa' => 'Empresa Prueba ' . date('Y-m-d H:i:s'),
            'rol' => 'administrador',
            'es_administrador' => true,
        ]);

        echo "✅ Empresa creada:\n";
        echo "ID: {$empresa->id}\n";
        echo "Nombre: {$empresa->nombre_empresa}\n\n";

        // Agregar automáticamente al dueño como integrante (simulando el controlador)
        $permisosAdmin = [
            'gestionar_usuarios',
            'ver_reportes',
            'configurar_sistema',
            'gestionar_permisos',
            'acceso_total'
        ];

        echo "🔄 Agregando al dueño como integrante administrador...\n";

        IntegrantesEmpresa::create([
            'iduser' => $user->id,
            'empresa_id' => $empresa->id,
            'rol' => 'administrador',
            'permisos' => $permisosAdmin,
        ]);

        echo "✅ Integrante agregado automáticamente\n\n";
    }

    // Verificar los integrantes de la empresa
    echo "📊 VERIFICACIÓN DE INTEGRANTES:\n";
    echo "===============================\n";

    $integrantes = IntegrantesEmpresa::where('empresa_id', $empresa->id)->get();

    if ($integrantes->count() > 0) {
        echo "✅ Total de integrantes: {$integrantes->count()}\n\n";

        foreach ($integrantes as $integrante) {
            echo "👥 INTEGRANTE:\n";
            echo "ID: {$integrante->id}\n";
            echo "User ID: {$integrante->iduser}\n";
            echo "Rol: {$integrante->rol}\n";
            echo "Permisos: " . implode(', ', $integrante->permisos) . "\n";
            echo "Fecha: {$integrante->created_at}\n";

            // Verificar si es el dueño
            if ($integrante->iduser === $empresa->iduser) {
                echo "👑 ES EL DUEÑO DE LA EMPRESA\n";
            }
            echo "---\n";
        }

        echo "🎉 PRUEBA EXITOSA: El sistema automáticamente agrega al dueño como integrante administrador\n";
    } else {
        echo "❌ No se encontraron integrantes para la empresa\n";
    }

} catch (Exception $e) {
    echo "❌ Error durante la prueba: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>
