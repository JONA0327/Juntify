<?php

// Agregamos el PATH para usar artisan de forma directa
require_once 'bootstrap/app.php';

$app = app();

// Obtener usuario y plan
$user = App\Models\User::first();
$plan = App\Models\Plan::where('code', 'basico')->first();

echo "Usuario: " . $user->email . "\n";
echo "Plan: " . $plan->name . " - $" . $plan->price . "\n";

// Probar el servicio MercadoPago
$service = new App\Services\MercadoPagoService();

try {
    $preference = $service->createPreferenceForPlan($user, $plan);
    echo "✅ Preferencia creada exitosamente!\n";
    echo "ID: " . $preference['id'] . "\n";
    echo "Init Point: " . $preference['init_point'] . "\n";
} catch (Exception $e) {
    echo "❌ Error al crear preferencia:\n";
    echo "Mensaje: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . ":" . $e->getLine() . "\n";

    // Si es un error de MercadoPago, veamos los detalles
    if (method_exists($e, 'getResponse')) {
        echo "Respuesta de MP: " . $e->getResponse() . "\n";
    }
}
