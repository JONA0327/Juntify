<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class VoiceBiometricMatcher
{
    /**
     * Compara un embedding de voz con los perfiles de usuarios de la reunión
     * 
     * @param array $audioEmbedding Embedding extraído del audio de la reunión (76 o 96 dimensiones)
     * @param array $userIds IDs de usuarios participantes de la reunión
     * @param float $threshold Umbral de similitud (0-1, default 0.75)
     * @return array|null ['user_id' => int, 'confidence' => float, 'user_name' => string] o null
     */
    public function matchSpeaker(array $audioEmbedding, array $userIds, float $threshold = 0.75): ?array
    {
        if (empty($audioEmbedding) || empty($userIds)) {
            return null;
        }

        $bestMatch = null;
        $highestSimilarity = 0;

        // Obtener usuarios con perfiles de voz configurados
        $usersWithVoice = User::whereIn('id', $userIds)
            ->whereNotNull('voice_embedding')
            ->get();

        foreach ($usersWithVoice as $user) {
            $userEmbedding = $this->normalizeEmbedding($user->voice_embedding);
            
            if (!is_array($userEmbedding) || count($userEmbedding) < 76) {
                Log::warning('Invalid voice embedding for user', [
                    'user_id' => $user->id,
                    'embedding_type' => gettype($userEmbedding),
                    'embedding_count' => is_array($userEmbedding) ? count($userEmbedding) : 0,
                ]);
                continue;
            }

            // Calcular similitud coseno
            $audioEmbeddingNormalized = $this->normalizeEmbedding($audioEmbedding);
            if (!is_array($audioEmbeddingNormalized)) {
                continue;
            }

            $similarity = $this->cosineSimilarity($audioEmbeddingNormalized, $userEmbedding);

            Log::debug('Voice similarity calculated', [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'similarity' => $similarity,
                'threshold' => $threshold,
            ]);

            if ($similarity > $highestSimilarity && $similarity >= $threshold) {
                $highestSimilarity = $similarity;
                $bestMatch = [
                    'user_id' => $user->id,
                    'confidence' => $similarity,
                    'user_name' => $user->name,
                ];
            }
        }

        if ($bestMatch) {
            Log::info('Speaker matched successfully', $bestMatch);
        } else {
            Log::info('No speaker match found', [
                'threshold' => $threshold,
                'highest_similarity' => $highestSimilarity,
                'users_checked' => $usersWithVoice->count(),
            ]);
        }

        return $bestMatch;
    }

    /**
     * Calcula la similitud coseno entre dos vectores
     * 
     * @param array $vec1 Vector 1
     * @param array $vec2 Vector 2
     * @return float Similitud (0-1)
     */
    private function cosineSimilarity(array $vec1, array $vec2): float
    {
        if (count($vec1) !== count($vec2)) {
            $min = min(count($vec1), count($vec2));
            $vec1 = array_slice($vec1, 0, $min);
            $vec2 = array_slice($vec2, 0, $min);
        }

        $dotProduct = 0;
        $magnitude1 = 0;
        $magnitude2 = 0;

        for ($i = 0; $i < count($vec1); $i++) {
            $dotProduct += $vec1[$i] * $vec2[$i];
            $magnitude1 += $vec1[$i] ** 2;
            $magnitude2 += $vec2[$i] ** 2;
        }

        $magnitude1 = sqrt($magnitude1);
        $magnitude2 = sqrt($magnitude2);

        if ($magnitude1 == 0 || $magnitude2 == 0) {
            return 0.0;
        }

        return $dotProduct / ($magnitude1 * $magnitude2);
    }

    private function normalizeEmbedding($embedding): ?array
    {
        if (!is_array($embedding)) {
            return null;
        }

        $embedding = array_values($embedding);
        $embedding = array_map(static fn ($value) => (float) $value, $embedding);

        if (count($embedding) < 76) {
            return null;
        }

        if (count($embedding) > 96) {
            $embedding = array_slice($embedding, 0, 96);
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

    /**
     * Procesa los segmentos de AssemblyAI y asigna nombres de usuarios
     * 
     * @param array $utterances Utterances de AssemblyAI
     * @param array $userIds IDs de usuarios participantes
     * @param float $threshold Umbral de similitud
     * @return array Utterances con speaker_name asignado
     */
    public function assignSpeakerNames(array $utterances, array $userIds, float $threshold = 0.75): array
    {
        // Mapa de speaker label a user info
        $speakerMap = [];

        foreach ($utterances as &$utterance) {
            $speaker = $utterance['speaker'] ?? null;
            
            if (!$speaker) {
                continue;
            }

            // Si ya tenemos este speaker mapeado, usar el mismo
            if (isset($speakerMap[$speaker])) {
                $utterance['speaker_name'] = $speakerMap[$speaker]['name'];
                $utterance['speaker_user_id'] = $speakerMap[$speaker]['user_id'];
                $utterance['speaker_confidence'] = $speakerMap[$speaker]['confidence'];
                continue;
            }

            // Por ahora, sin el audio del segmento específico, no podemos extraer embedding
            // Esta funcionalidad requiere procesar el audio por segmentos
            // Por ahora dejamos el speaker como está
            $utterance['speaker_name'] = "Hablante " . $speaker;
        }

        return $utterances;
    }
}
