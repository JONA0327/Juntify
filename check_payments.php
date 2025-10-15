<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Payment;

echo "=== ÚLTIMOS PAGOS REGISTRADOS ===\n";

$payments = Payment::latest()->take(5)->get([
    'id',
    'external_reference',
    'status',
    'amount',
    'currency',
    'payment_method',
    'created_at'
]);

if ($payments->count() > 0) {
    foreach ($payments as $payment) {
        echo "ID: {$payment->id}\n";
        echo "Referencia: {$payment->external_reference}\n";
        echo "Estado: {$payment->status}\n";
        echo "Monto: {$payment->amount} {$payment->currency}\n";
        echo "Método: {$payment->payment_method}\n";
        echo "Fecha: {$payment->created_at}\n";
        echo "----------------------------\n";
    }
} else {
    echo "No se encontraron pagos registrados.\n";
}

echo "Total de pagos: " . Payment::count() . "\n";
