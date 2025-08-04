<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TranscriptionController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'audio'    => 'required|file',
            'language' => 'nullable|in:es,en,fr,de',
        ]);

        $language = $request->input('language', 'es');
        $apiKey = config('services.assemblyai.api_key');

        if (empty($apiKey)) {
            return response()->json(['error' => 'AssemblyAI API key missing'], 500);
        }

        $filePath = $request->file('audio')->getRealPath();
        $handle   = fopen($filePath, 'rb');
        $uploadUrl = null;

        while (!feof($handle)) {
            $chunk = fread($handle, 5 * 1024 * 1024); // 5MB chunks
            if ($chunk === false) {
                fclose($handle);
                return response()->json(['error' => 'Failed to read audio file'], 500);
            }

            try {
                $response = Http::timeout(120)->connectTimeout(60)
                    ->withHeaders([
                        'authorization' => $apiKey,
                        'content-type'  => 'application/octet-stream',
                    ])
                    ->withBody($chunk, 'application/octet-stream')
                    ->post($uploadUrl ?? 'https://api.assemblyai.com/v2/upload');
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                fclose($handle);
                return response()->json(['error' => 'Failed to connect to AssemblyAI'], 504);
            }

            if (!$response->successful()) {
                fclose($handle);
                return response()->json(['error' => 'Upload failed', 'details' => $response->json()], 500);
            }

            $uploadUrl = $response->json('upload_url');
        }

        fclose($handle);

        $transcription = Http::withHeaders([
            'authorization' => $apiKey,
        ])
            ->post('https://api.assemblyai.com/v2/transcript', [
                'audio_url'      => $uploadUrl,
                'speaker_labels' => true,
                'punctuate'      => true,
                'format_text'    => true,
                'language_code'  => $language,
            ]);

        if (!$transcription->successful()) {
            return response()->json(['error' => 'Transcription request failed', 'details' => $transcription->json()], 500);
        }

        return response()->json(['id' => $transcription->json('id')]);
    }

    public function show(string $id)
    {
        $apiKey = config('services.assemblyai.api_key');

        $response = Http::withHeaders([
            'authorization' => $apiKey,
        ])
            ->get("https://api.assemblyai.com/v2/transcript/{$id}");

        if (!$response->successful()) {
            return response()->json(['error' => 'Status check failed', 'details' => $response->json()], 500);
        }

        return $response->json();
    }
}
