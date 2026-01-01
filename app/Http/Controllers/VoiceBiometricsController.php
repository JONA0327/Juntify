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
            $process = new Process([
                'python3',
                base_path('tools/enroll_voice.py'),
                $fullPath,
            ]);
            $process->setTimeout(120);
            $process->run();

            if (!$process->isSuccessful()) {
                $message = trim($process->getErrorOutput()) ?: 'No se pudo procesar el audio.';
                return response()->json(['message' => $message], 422);
            }

            $embedding = json_decode(trim($process->getOutput()), true);
            if (!is_array($embedding)) {
                return response()->json(['message' => 'La respuesta del procesador de voz es inválida.'], 422);
            }

            $user->voice_embedding = $embedding;
            $user->save();

            return response()->json(['message' => 'Huella de voz guardada correctamente.']);
        } finally {
            if ($tempPath) {
                Storage::delete($tempPath);
            }
        }
    }
}
