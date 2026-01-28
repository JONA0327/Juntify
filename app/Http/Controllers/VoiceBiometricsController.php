<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class VoiceBiometricsController extends Controller
{
    public function storeEnrollment(Request $request)
    {
        $request->validate([
            'audio' => 'required|file|mimes:webm,wav,mp3,ogg,m4a|max:51200',
        ]);

        $user = $request->user();
        $extension = $request->file('audio')->getClientOriginalExtension();
        $tempPath = $request->file('audio')->storeAs(
            'temp',
            'voice_enrollment_' . $user->id . '_' . Str::uuid() . '.' . $extension
        );
        $fullPath = storage_path('app/' . $tempPath);

        try {
            $pythonPath = config('audio.python_bin', env('PYTHON_BIN', 'python'));
            
            $process = new Process([
                $pythonPath,
                base_path('tools/enroll_voice_simple.py'),
                $fullPath,
            ]);
            $process->setTimeout(120);
            
            // Pasar variables de entorno necesarias
            $env = [
                'FFMPEG_BIN' => config('audio.ffmpeg_bin', env('FFMPEG_BIN', 'ffmpeg')),
                'PYTHONIOENCODING' => 'utf-8',
                'LIBROSA_CACHE_DIR' => '',
                'LIBROSA_CACHE_LEVEL' => '0',
                'JOBLIB_START_METHOD' => 'loky',
            ];
            $process->setEnv($env);
            
            $process->run();

            if (!$process->isSuccessful()) {
                $errorOutput = $process->getErrorOutput();
                // Asegurar UTF-8
                $errorOutput = mb_convert_encoding($errorOutput, 'UTF-8', 'UTF-8');
                $message = trim($errorOutput) ?: 'No se pudo procesar el audio.';
                return response()->json(['message' => $message], 422);
            }

            $output = $process->getOutput();
            // Asegurar UTF-8
            $output = mb_convert_encoding($output, 'UTF-8', 'UTF-8');
            $embedding = json_decode(trim($output), true);
            
            \Log::info('Voice embedding received', [
                'raw_output' => substr($output, 0, 200),
                'embedding_type' => gettype($embedding),
                'embedding_count' => is_array($embedding) ? count($embedding) : 0,
                'user_id' => $user->id,
            ]);
            
            if (!is_array($embedding)) {
                \Log::error('Invalid embedding format', ['output' => $output]);
                return response()->json(['message' => 'La respuesta del procesador de voz es inválida.'], 422);
            }

            $normalizedEmbedding = $this->normalizeEmbedding($embedding);
            if ($normalizedEmbedding === null) {
                \Log::error('Invalid embedding size returned', [
                    'user_id' => $user->id,
                    'embedding_count' => is_array($embedding) ? count($embedding) : 0,
                ]);
                return response()->json(['message' => 'La huella de voz no tiene el formato esperado.'], 422);
            }

            $user->voice_embedding = $normalizedEmbedding;
            $saved = $user->save();
            
            \Log::info('Voice embedding saved', [
                'user_id' => $user->id,
                'saved' => $saved,
                'embedding_size' => count($normalizedEmbedding),
            ]);

            return response()->json(['message' => 'Huella de voz guardada correctamente.']);
        } finally {
            if ($tempPath) {
                Storage::delete($tempPath);
            }
        }
    }

    private function normalizeEmbedding(array $embedding): ?array
    {
        $embedding = array_values($embedding);
        $embedding = array_map(static fn ($value) => (float) $value, $embedding);

        if (count($embedding) < 76) {
            return null;
        }

        if (count($embedding) > 76) {
            $embedding = array_slice($embedding, 0, 76);
        }

        $mean = array_sum($embedding) / count($embedding);
        $variance = 0.0;
        foreach ($embedding as $value) {
            $variance += ($value - $mean) ** 2;
        }
        $std = sqrt($variance / count($embedding));

        if ($std <= 0) {
            return $embedding;
        }

        return array_map(static fn ($value) => ($value - $mean) / $std, $embedding);
    }

    public function status(Request $request)
    {
        try {
            $user = $request->user();
            $embedding = $user->voice_embedding;
            $configured = !empty($embedding) && is_array($embedding);
            
            return response()->json([
                'configured' => $configured,
                'embedding_size' => $configured ? count($embedding) : 0,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error checking voice profile status', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);
            
            return response()->json([
                'configured' => false,
                'embedding_size' => 0,
            ]);
        }
    }

    public function remove(Request $request)
    {
        $user = $request->user();
        $user->voice_embedding = null;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Perfil de voz eliminado correctamente.'
        ]);
    }
}
