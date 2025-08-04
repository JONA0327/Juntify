<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;

class TranscriptionController extends Controller
{
    public function store(Request $request)
    {
        set_time_limit(300);

        $request->validate([
            'audio'    => 'required|file',
            'language' => 'nullable|in:es,en,fr,de',
        ]);

        $language = $request->input('language', 'es');
        $apiKey = config('services.assemblyai.api_key');

        if (empty($apiKey)) {
            return response()->json(['error' => 'AssemblyAI API key missing'], 500);
        }

        $filePath  = $request->file('audio')->getRealPath();
        $audioData = file_get_contents($filePath);
        if ($audioData === false) {
            return response()->json(['error' => 'Failed to read audio file'], 500);
        }

        try {
            $http = Http::timeout(300)->connectTimeout(300)
                ->withHeaders([
                    'authorization' => $apiKey,
                    'content-type'  => 'application/octet-stream',
                ])
                ->withBody($audioData, 'application/octet-stream');

            if (!config('services.assemblyai.verify_ssl', true)) {
                $http = $http->withoutVerifying();
            } else {
                $http = $http->withOptions(['verify' => config('services.assemblyai.verify_ssl', true)]);
            }

            $response = $http->post('https://api.assemblyai.com/v2/upload');
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return response()->json(['error' => 'Failed to connect to AssemblyAI'], 504);
        } catch (RequestException $e) {
            return response()->json(['error' => 'SSL certificate validation failed'], 500);
        }

        if (!$response->successful()) {
            return response()->json(['error' => 'Upload failed', 'details' => $response->json()], 500);
        }

        $uploadUrl = $response->json('upload_url');

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
