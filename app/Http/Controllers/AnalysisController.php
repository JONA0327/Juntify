<?php

namespace App\Http\Controllers;

use App\Models\Analyzer;
use Illuminate\Http\Request;
use OpenAI\Laravel\Facade as OpenAI;

class AnalysisController extends Controller
{
    public function analyze(Request $request)
    {
        $data = $request->validate([
            'analyzer_id' => 'required|string',
            'transcript'  => 'required|string',
        ]);

        $analyzer = Analyzer::findOrFail($data['analyzer_id']);

        $userPrompt = str_replace('{transcription}', $data['transcript'], $analyzer->user_prompt_template);

        $client = OpenAI::client(config('services.openai.api_key'));

        $response = $client->chat()->create([
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'system', 'content' => $analyzer->system_prompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'temperature' => $analyzer->temperature ?? 0.6,
            'response_format' => ['type' => 'json_object'],
        ]);

        $content = $response->choices[0]->message->content ?? '{}';
        $json = json_decode($content, true);

        return response()->json($json);
    }
}
