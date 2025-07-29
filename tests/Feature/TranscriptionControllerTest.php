<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class TranscriptionController extends Controller
{
    public function store(Request $request)
    {
        try {
            Log::info('✅ Entró correctamente al método store');
             return response()->json(['mensaje' => 'test ok']);

            if (!$request->hasFile('audio')) {
                Log::warning('No se recibió archivo de audio');
                return response()->json(['error' => 'No se recibió archivo de audio'], 400);
            }

            $audio = $request->file('audio');

            if (!$audio->isValid()) {
                Log::warning('Archivo recibido inválido');
                return response()->json(['error' => 'El archivo de audio es inválido'], 400);
            }

            Log::info('Archivo recibido:', [
                'name' => $audio->getClientOriginalName(),
                'mime' => $audio->getClientMimeType(),
                'size' => $audio->getSize()
            ]);

            // Guardar archivo temporalmente
            $path = $audio->store('audios_temporales');
            $fullPath = storage_path("app/{$path}");

            Log::info('Archivo guardado temporalmente en: ' . $fullPath);

            // Subir archivo a AssemblyAI
            $uploadResponse = Http::withHeaders([
                'authorization' => env('ASSEMBLYAI_API_KEY'),
            ])->attach(
                'file', fopen($fullPath, 'r'), $audio->getClientOriginalName()
            )->post('https://api.assemblyai.com/v2/upload');

            if (!$uploadResponse->successful()) {
                Log::error('Error al subir a AssemblyAI', [
                    'status' => $uploadResponse->status(),
                    'body' => $uploadResponse->body()
                ]);
                return response()->json([
                    'error' => 'Error al subir audio a AssemblyAI',
                    'details' => $uploadResponse->body()
                ], 500);
            }

            $uploadUrl = $uploadResponse->json('upload_url');
            Log::info('Archivo subido correctamente a AssemblyAI:', ['upload_url' => $uploadUrl]);

            // Crear la transcripción
            $transcriptionResponse = Http::withHeaders([
                'authorization' => env('ASSEMBLYAI_API_KEY'),
                'content-type' => 'application/json'
            ])->post('https://api.assemblyai.com/v2/transcript', [
                'audio_url' => $uploadUrl,
                'speaker_labels' => true,
                'auto_highlights' => true
            ]);

            if (!$transcriptionResponse->successful()) {
                Log::error('Error al iniciar transcripción en AssemblyAI', [
                    'status' => $transcriptionResponse->status(),
                    'body' => $transcriptionResponse->body()
                ]);
                return response()->json([
                    'error' => 'Error al iniciar transcripción',
                    'details' => $transcriptionResponse->body()
                ], 500);
            }

            $transcriptionId = $transcriptionResponse->json('id');

            Log::info('Transcripción iniciada correctamente', ['id' => $transcriptionId]);

            return response()->json([
                'message' => 'Transcripción iniciada',
                'id' => $transcriptionId
            ]);

        } catch (\Throwable $e) {
            Log::error('Excepción en store(): ' . $e->getMessage());
            return response()->json([
                'error' => 'Error interno del servidor',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
