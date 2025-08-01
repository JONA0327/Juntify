<?php

namespace App\Http\Controllers;

use App\Models\Analyzer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenAI;

class AnalysisController extends Controller
{
    public function analyze(Request $request)
    {
        $data = $request->validate([
            'analyzer_id' => 'required|string',
            'transcript'  => 'required|string',
        ]);

        $analyzer = Analyzer::find($data['analyzer_id']);
        if (! $analyzer) {
            return response()->json(['error' => 'Analyzer not found'], 404);
        }

        if (! str_contains($analyzer->user_prompt_template ?? '', '{transcription}')) {
            return response()->json([
                'error' => 'La plantilla de prompt no contiene el marcador {transcription}',
            ], 400);
        }

        $userPrompt = str_replace('{transcription}', $data['transcript'], $analyzer->user_prompt_template);
        if (empty(trim($userPrompt))) {
            return response()->json(['error' => 'Prompt de usuario vacío'], 400);
        }

        Log::info('Prompt enviado a OpenAI', ['prompt' => $userPrompt]);

        $client = OpenAI::client(config('services.openai.api_key'));

        try {
            $response = $client->chat()->create([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'system', 'content' => $analyzer->system_prompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => isset($analyzer->temperature) ? (float)$analyzer->temperature : 0.6,
                'response_format' => ['type' => 'json_object'],
            ]);
        } catch (\Exception $e) {
            Log::error('Error en OpenAI', ['exception' => $e->getMessage()]);
            return response()->json(['error' => 'Error en OpenAI: ' . $e->getMessage()], 500);
        }

        $content = $response->choices[0]->message->content ?? '{}';
        $json = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Respuesta OpenAI no es JSON válido', [
                'content' => $content,
                'error' => json_last_error_msg(),
            ]);
            return response()->json([
                'error' => 'Respuesta de OpenAI no es JSON válido',
            ], 500);
        }

        return response()->json($json);
    }
}
