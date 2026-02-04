<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "ESTRUCTURA DE LA TABLA 'contacts'\n";
echo "==================================\n";
try {
    $columns = Illuminate\Support\Facades\DB::select('DESCRIBE contacts');
    foreach($columns as $col) {
        echo "- {$col->Field} ({$col->Type})\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nEJEMPLO DE CONTACTOS:\n";
echo "=====================\n";
try {
    $contacts = Illuminate\Support\Facades\DB::table('contacts')->limit(3)->get();
    foreach($contacts as $contact) {
        echo "ID: {$contact->id}, user_id: " . ($contact->user_id ?? 'N/A') . ", name: " . ($contact->name ?? 'N/A') . "\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
