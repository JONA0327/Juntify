<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class DiagnoseAssemblyAI extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'diagnose:assemblyai';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Diagnosticar la configuración y conexión con AssemblyAI';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Diagnosticando configuración de AssemblyAI...');
        $this->newLine();

        // 1. Verificar variables de entorno
        $apiKey = config('services.assemblyai.api_key');
        $timeout = config('services.assemblyai.timeout');
        $connectTimeout = config('services.assemblyai.connect_timeout');
        $verifySsl = config('services.assemblyai.verify_ssl');

        $this->info('📋 Configuración actual:');
        $this->table([
            'Variable', 'Valor', 'Estado'
        ], [
            ['API Key', $apiKey ? substr($apiKey, 0, 10) . '...' : 'NO CONFIGURADA', $apiKey ? '✅' : '❌'],
            ['Timeout', $timeout . 's', '✅'],
            ['Connect Timeout', $connectTimeout . 's', '✅'],
            ['Verify SSL', $verifySsl ? 'Sí' : 'No', '✅']
        ]);

        if (!$apiKey) {
            $this->error('❌ La API key de AssemblyAI no está configurada.');
            $this->info('💡 Agrega ASSEMBLYAI_API_KEY=tu_api_key en el archivo .env');
            return 1;
        }

        $this->newLine();
        $this->info('🌐 Probando conexión con AssemblyAI...');

        try {
            // Test básico: verificar API key y saldo
            $response = Http::withHeaders([
                'Authorization' => $apiKey,
                'Content-Type' => 'application/json'
            ])->timeout($connectTimeout)->get('https://api.assemblyai.com/v2/transcript');

            $this->info('✅ Conexión exitosa con AssemblyAI');
            $this->info('📊 Status: ' . $response->status());

            if ($response->successful()) {
                $this->info('✅ API key válida');
            } else {
                $this->warn('⚠️  Respuesta no exitosa: ' . $response->status());
                $responseBody = $response->body();
                $this->info('📦 Respuesta: ' . $responseBody);

                // Verificar si es problema de saldo
                if (str_contains($responseBody, 'account balance is negative')) {
                    $this->error('💳 PROBLEMA DE SALDO: La cuenta de AssemblyAI tiene saldo negativo');
                    $this->info('💡 Solución: Recarga créditos en tu cuenta de AssemblyAI');
                }
            }

        } catch (\Exception $e) {
            $this->error('❌ Error de conexión: ' . $e->getMessage());

            if (str_contains($e->getMessage(), 'SSL')) {
                $this->info('💡 Intenta desactivar SSL temporalmente: ASSEMBLYAI_VERIFY_SSL=false');
            }

            if (str_contains($e->getMessage(), 'timeout')) {
                $this->info('💡 Intenta aumentar el timeout: ASSEMBLYAI_TIMEOUT=600');
            }

            return 1;
        }

        $this->newLine();
        $this->info('🎯 Test de subida de archivo...');

        try {
            // Test de upload endpoint
            $uploadResponse = Http::withHeaders([
                'Authorization' => $apiKey,
            ])->timeout($connectTimeout)->post('https://api.assemblyai.com/v2/upload', [
                'data' => 'test'
            ]);

            if ($uploadResponse->successful()) {
                $this->info('✅ Endpoint de subida funcional');
            } else {
                $this->warn('⚠️  Endpoint de subida con problema: ' . $uploadResponse->status());
                $this->info('📦 Respuesta: ' . $uploadResponse->body());
            }

        } catch (\Exception $e) {
            $this->error('❌ Error en endpoint de subida: ' . $e->getMessage());
        }

        $this->newLine();
        $this->info('🏁 Diagnóstico completado');

        return 0;
    }
}
