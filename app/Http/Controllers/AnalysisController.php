<?php
namespace App\Http\Controllers;
use App\Models\Analyzer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use OpenAI; // Importar el facade de OpenAI
use App\Http\Controllers\Controller;


class AnalysisController extends Controller
{
    public function analyze(Request $request)
    {
        Log::info('Análisis iniciado', [
            'request_data' => $request->all(),
            'user' => Auth::user()->username ?? 'unknown'
        ]);

        $data = $request->validate([
            'analyzer_id' => 'required|string',
            'transcript'  => 'required|string',
        ]);

        $analyzer = Analyzer::find($data['analyzer_id']);
        if (! $analyzer) {
            Log::error('Analyzer no encontrado', [
                'analyzer_id' => $data['analyzer_id'],
                'available_analyzers' => Analyzer::all(['id', 'name'])->toArray()
            ]);
            return response()->json(['error' => 'Analyzer not found'], 404);
        }

        if (! str_contains($analyzer->user_prompt_template ?? '', '{transcription}')) {
            Log::error('Template sin marcador de transcripción', [
                'analyzer_id' => $data['analyzer_id'],
                'template' => $analyzer->user_prompt_template
            ]);
            return response()->json([
                'error' => 'La plantilla de prompt no contiene el marcador {transcription}',
            ], 400);
        }

        $userPrompt = str_replace('{transcription}', $data['transcript'], $analyzer->user_prompt_template);
        if (empty(trim($userPrompt))) {
            return response()->json(['error' => 'Prompt de usuario vacío'], 400);
        }

        Log::info('Prompt enviado a OpenAI', ['prompt' => substr($userPrompt, 0, 100)]);

        $client = OpenAI::client(config('services.openai.api_key'));

        $user = Auth::user();
        $model = 'gpt-4o-mini';
        if ($user && isset($user->role) && $user->role === 'free') {
            $model = 'gpt-3.5-turbo';
        }

        try {
            $response = $client->chat()->create([
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $analyzer->system_prompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => isset($analyzer->temperature) ? (float)$analyzer->temperature : 0.6,
                'response_format' => ['type' => 'json_object'],
            ]);
        } catch (\Exception $e) {
            Log::error('Error en OpenAI', ['exception' => $e->getMessage()]);

            // Mensajes de error más específicos
            $errorMessage = $e->getMessage();
            if (str_contains($errorMessage, 'Incorrect API key')) {
                return response()->json([
                    'error' => 'API key de OpenAI inválida. Por favor verifica tu configuración.',
                    'details' => 'La clave de API de OpenAI no es válida o ha expirado.'
                ], 500);
            } elseif (str_contains($errorMessage, 'insufficient_quota')) {
                return response()->json([
                    'error' => 'Cuota de OpenAI agotada. Por favor verifica tu plan de OpenAI.',
                    'details' => 'Has excedido tu cuota mensual de OpenAI.'
                ], 500);
            } else {
                return response()->json([
                    'error' => 'Error al conectar con OpenAI: ' . $errorMessage,
                    'details' => 'Revisa tu conexión a internet y configuración de API.'
                ], 500);
            }
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
