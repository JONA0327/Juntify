<?php
// Script para probar el endpoint create-preference directamente

// Headers necesarios
$headers = [
    'Content-Type: application/json',
    'Accept: application/json',
];

// URL del endpoint - usando Laragon
$url = 'http://juntify.test/subscription/create-preference';

// Datos de prueba - usando el ID del plan básico
$data = [
    'plan_id' => 1  // Plan básico
];

// Configurar cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

// Ejecutar la request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Error: $error\n";
echo "Response: $response\n";

// Si la respuesta es JSON, formatearla
if ($response && $httpCode == 200) {
    $responseData = json_decode($response, true);
    if ($responseData) {
        echo "\nFormatted Response:\n";
        print_r($responseData);
    }
}
