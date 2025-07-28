<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TranscriptionController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'audio' => 'required|file',
        ]);

        $apiKey = config('services.assemblyai.api_key');

        $upload = Http::withToken($apiKey)
            ->attach('file', fopen($request->file('audio')->getRealPath(), 'r'))
            ->post('https://api.assemblyai.com/v2/upload');

        if (!$upload->successful()) {
            return response()->json(['error' => 'Upload failed', 'details' => $upload->json()], 500);
        }

        $transcription = Http::withToken($apiKey)
            ->post('https://api.assemblyai.com/v2/transcript', [
                'audio_url'      => $upload->json('upload_url'),
                'speaker_labels' => true,
            ]);

        if (!$transcription->successful()) {
            return response()->json(['error' => 'Transcription request failed', 'details' => $transcription->json()], 500);
        }

        return response()->json(['id' => $transcription->json('id')]);
    }

    public function show(string $id)
    {
        $apiKey = config('services.assemblyai.api_key');

        $response = Http::withToken($apiKey)
            ->get("https://api.assemblyai.com/v2/transcript/{$id}");

        if (!$response->successful()) {
            return response()->json(['error' => 'Status check failed', 'details' => $response->json()], 500);
        }

        return $response->json();
    }
}
